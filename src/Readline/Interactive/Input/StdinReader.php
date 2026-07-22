<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Input;

/**
 * Read characters from stdin.
 */
class StdinReader
{
    private const SGR_MOUSE_RX = '/^\033\[<(\d+);(\d+);(\d+)([Mm])$/';

    /** @var resource */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct($stream = \STDIN)
    {
        $this->stream = $stream;
    }

    /**
     * Check if there's pending input (useful for paste detection).
     */
    public function hasPendingInput(): bool
    {
        // Memory streams don't support stream_select.
        $meta = \stream_get_meta_data($this->stream);
        if (isset($meta['stream_type']) && $meta['stream_type'] === 'MEMORY') {
            return false;
        }

        \stream_set_blocking($this->stream, false);

        $read = [$this->stream];
        $write = null;
        $except = null;

        $ready = @\stream_select($read, $write, $except, 0, 0);

        \stream_set_blocking($this->stream, true);

        return $ready > 0;
    }

    /**
     * Read all available input (used for handling pastes).
     *
     * @return string The pasted content
     */
    public function readPastedContent(): string
    {
        // Memory streams don't support stream_select.
        $meta = \stream_get_meta_data($this->stream);
        if (isset($meta['stream_type']) && $meta['stream_type'] === 'MEMORY') {
            $content = '';
            while (($char = \fgetc($this->stream)) !== false) {
                $content .= $char;
            }

            return $content;
        }

        \stream_set_blocking($this->stream, false);

        $content = '';
        $timeout = 50000;
        $start = \microtime(true);

        while ((\microtime(true) - $start) < ($timeout / 1000000)) {
            $char = \fgetc($this->stream);

            if ($char === false) {
                $read = [$this->stream];
                $write = null;
                $except = null;
                if (@\stream_select($read, $write, $except, 0, 1000) > 0) {
                    continue;
                }
                break;
            }

            $content .= $char;
        }

        \stream_set_blocking($this->stream, true);

        return $content;
    }

    /**
     * Read a single event from the input stream.
     */
    public function readEvent(): InputEvent
    {
        $char = \fgetc($this->stream);

        if ($char === false) {
            return new EofEvent();
        }

        // Escape sequences must be handled before paste detection.
        if ($char === "\033") {
            $escapeKey = $this->readEscapeSequence($char);

            if ($escapeKey->getValue() === "\033[200~") {
                return $this->readBracketedPaste();
            }

            // SGR mouse reports (\033[<button;col;row{M,m}) are reshaped
            // into mouse events with a friendly action (e.g. 'wheel-up').
            if (\preg_match(self::SGR_MOUSE_RX, $escapeKey->getValue(), $m)) {
                $action = $this->mouseAction((int) $m[1], $m[4]);
                if ($action !== null) {
                    return new MouseEvent($action, (int) $m[2], (int) $m[3]);
                }
            }

            return $escapeKey;
        }

        if ($this->hasPendingInput()) {
            $pastedContent = $char.$this->readPastedContent();

            // No ungetc support in PHP, so return any buffered content as a paste.
            if (\strlen($pastedContent) > 1) {
                return new PasteEvent($pastedContent);
            }
        }

        // CR/LF are handled as regular chars, not control characters.
        $ord = \ord($char);
        if ($ord < 32 && $char !== "\n" && $char !== "\r") {
            return new KeyEvent($char, KeyEvent::TYPE_CONTROL);
        }

        if ($ord === 127) {
            return new KeyEvent($char, KeyEvent::TYPE_CONTROL);
        }

        return new KeyEvent($char, KeyEvent::TYPE_CHAR);
    }

    /**
     * Map an SGR mouse button code + event letter into a friendly action.
     * Modifier bits in the high nibble are ignored. Returns
     * null for buttons we don't care to surface.
     */
    private function mouseAction(int $button, string $event): ?string
    {
        if ($event === 'M' && ($button & 0b00100000) !== 0) {
            return MouseEvent::ACTION_MOVE;
        }

        // Mask off the modifier (shift/meta/ctrl) and motion bits.
        // Wheel events live in 64..67; primary buttons in 0..2.
        $base = $button & 0b11000011;

        if ($event === 'M') {
            switch ($base) {
                case 0:  return MouseEvent::ACTION_PRESS_LEFT;
                case 1:  return MouseEvent::ACTION_PRESS_MIDDLE;
                case 2:  return MouseEvent::ACTION_PRESS_RIGHT;
                case 64: return MouseEvent::ACTION_WHEEL_UP;
                case 65: return MouseEvent::ACTION_WHEEL_DOWN;
                case 66: return MouseEvent::ACTION_WHEEL_LEFT;
                case 67: return MouseEvent::ACTION_WHEEL_RIGHT;
            }
        } elseif ($event === 'm') {
            switch ($base) {
                case 0: return MouseEvent::ACTION_RELEASE_LEFT;
                case 1: return MouseEvent::ACTION_RELEASE_MIDDLE;
                case 2: return MouseEvent::ACTION_RELEASE_RIGHT;
            }
        }

        return null;
    }

    /**
     * Read an escape sequence from the input stream.
     */
    protected function readEscapeSequence(string $prefix): KeyEvent
    {
        \stream_set_blocking($this->stream, false);

        $sequence = $prefix;
        $timeout = 100000;
        $start = \microtime(true);

        while ((\microtime(true) - $start) < ($timeout / 1000000)) {
            $char = \fgetc($this->stream);

            if ($char === false) {
                \usleep(1000); // 1ms
                continue;
            }

            $sequence .= $char;

            if ($this->isCompleteEscapeSequence($sequence)) {
                break;
            }

            if (\strlen($sequence) > 32) {
                break;
            }
        }

        \stream_set_blocking($this->stream, true);

        return new KeyEvent($sequence, KeyEvent::TYPE_ESCAPE);
    }

    /**
     * Check if an escape sequence is complete.
     */
    protected function isCompleteEscapeSequence(string $sequence): bool
    {
        if (\strpos($sequence, "\033[<") === 0) {
            return (bool) \preg_match(self::SGR_MOUSE_RX, $sequence);
        }

        // Esc+Enter remaps (common in terminal keybind customization).
        if ($sequence === "\033\r" || $sequence === "\033\n") {
            return true;
        }

        // Arrow keys: \033[A-D, Home/End: \033[H, \033[F
        if (\preg_match('/^\033\[[A-DHF]$/', $sequence)) {
            return true;
        }

        // Function keys, Delete, bracketed paste: \033[NNN~ or \033[27;2;13~
        if (\preg_match('/^\033\[\d+(?:[;:]\d+)*~$/', $sequence)) {
            return true;
        }

        // Modified keys: \033[1;3C (Alt+Right), \033[1;5D (Ctrl+Left), etc.
        if (\preg_match('/^\033\[\d+(?:[;:]\d+)*[A-Za-z]$/', $sequence)) {
            return true;
        }

        // CSI-u protocol: \033[13;2u (Shift+Enter), \033[97;5u (Ctrl+A), etc.
        if (\preg_match('/^\033\[\d+(?:[;:]\d+)*u$/', $sequence)) {
            return true;
        }

        // SS3 keys: \033OM (keypad Enter), \033OA, etc.
        if (\preg_match('/^\033O[A-Z]$/', $sequence)) {
            return true;
        }

        // Alt+char: \033b, \033f, etc.
        if (\preg_match('/^\033[a-z]$/i', $sequence)) {
            return true;
        }

        return false;
    }

    /**
     * Read bracketed paste content.
     *
     * Called after detecting \033[200~ sequence.
     * Reads until \033[201~ is encountered.
     */
    protected function readBracketedPaste(): PasteEvent
    {
        $content = '';
        $escapeBuffer = '';

        while (true) {
            $char = \fgetc($this->stream);

            if ($char === false) {
                break;
            }

            if ($char === "\033") {
                $escapeBuffer = $char;

                \stream_set_blocking($this->stream, false);
                $timeout = 10000;
                $start = \microtime(true);

                while ((\microtime(true) - $start) < ($timeout / 1000000)) {
                    $nextChar = \fgetc($this->stream);

                    if ($nextChar === false) {
                        \usleep(500);
                        continue;
                    }

                    $escapeBuffer .= $nextChar;

                    if ($escapeBuffer === "\033[201~") {
                        \stream_set_blocking($this->stream, true);

                        return new PasteEvent($content);
                    }

                    if ($this->isCompleteEscapeSequence($escapeBuffer)) {
                        break;
                    }

                    if (\strlen($escapeBuffer) > 32) {
                        break;
                    }
                }

                \stream_set_blocking($this->stream, true);

                $content .= $escapeBuffer;
                $escapeBuffer = '';
            } else {
                $content .= $char;
            }
        }

        return new PasteEvent($content);
    }

    /**
     * Get the underlying stream resource.
     *
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }
}
