<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2017, Hoa community. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace Hoa\Console\Readline;

use Hoa\Consistency;
use Hoa\Console;
use Hoa\Ustring;

/**
 * Class \Hoa\Console\Readline.
 *
 * Read, edit, bind… a line from the input.
 *
 * @copyright  Copyright © 2007-2017 Hoa community
 * @license    New BSD License
 */
class Readline
{
    /**
     * State: continue to read.
     *
     * @const int
     */
    const STATE_CONTINUE = 1;

    /**
     * State: stop to read.
     *
     * @const int
     */
    const STATE_BREAK    = 2;

    /**
     * State: no output the current buffer.
     *
     * @const int
     */
    const STATE_NO_ECHO  = 4;

    /**
     * Current editing line.
     *
     * @var string
     */
    protected $_line           = null;

    /**
     * Current editing line seek.
     *
     * @var int
     */
    protected $_lineCurrent    = 0;

    /**
     * Current editing line length.
     *
     * @var int
     */
    protected $_lineLength     = 0;

    /**
     * Current buffer (most of the time, a char).
     *
     * @var string
     */
    protected $_buffer         = null;

    /**
     * Mapping.
     *
     * @var array
     */
    protected $_mapping        = [];

    /**
     * History.
     *
     * @var array
     */
    protected $_history        = [];

    /**
     * History current position.
     *
     * @var int
     */
    protected $_historyCurrent = 0;

    /**
     * History size.
     *
     * @var int
     */
    protected $_historySize    = 0;

    /**
     * Prefix.
     *
     * @var string
     */
    protected $_prefix         = null;

    /**
     * Autocompleter.
     *
     * @var \Hoa\Console\Readline\Autocompleter
     */
    protected $_autocompleter  = null;



    /**
     * Initialize the readline editor.
     *
     */
    public function __construct()
    {
        if (OS_WIN) {
            return;
        }

        $this->_mapping["\033[A"] = xcallable($this, '_bindArrowUp');
        $this->_mapping["\033[B"] = xcallable($this, '_bindArrowDown');
        $this->_mapping["\033[C"] = xcallable($this, '_bindArrowRight');
        $this->_mapping["\033[D"] = xcallable($this, '_bindArrowLeft');
        $this->_mapping["\001"]   = xcallable($this, '_bindControlA');
        $this->_mapping["\002"]   = xcallable($this, '_bindControlB');
        $this->_mapping["\005"]   = xcallable($this, '_bindControlE');
        $this->_mapping["\006"]   = xcallable($this, '_bindControlF');
        $this->_mapping["\010"]   =
        $this->_mapping["\177"]   = xcallable($this, '_bindBackspace');
        $this->_mapping["\027"]   = xcallable($this, '_bindControlW');
        $this->_mapping["\n"]     = xcallable($this, '_bindNewline');
        $this->_mapping["\t"]     = xcallable($this, '_bindTab');

        return;
    }

    /**
     * Read a line from the input.
     *
     * @param   string  $prefix    Prefix.
     * @return  string
     */
    public function readLine($prefix = null)
    {
        $input = Console::getInput();

        if (true === $input->eof()) {
            return false;
        }

        $direct = Console::isDirect($input->getStream()->getStream());
        $output = Console::getOutput();

        if (false === $direct || OS_WIN) {
            $out = $input->readLine();

            if (false === $out) {
                return false;
            }

            $out = substr($out, 0, -1);

            if (true === $direct) {
                $output->writeAll($prefix);
            } else {
                $output->writeAll($prefix . $out . "\n");
            }

            return $out;
        }

        $this->resetLine();
        $this->setPrefix($prefix);
        $read = [$input->getStream()->getStream()];
        $output->writeAll($prefix);

        while (true) {
            @stream_select($read, $write, $except, 30, 0);

            if (empty($read)) {
                $read = [$input->getStream()->getStream()];

                continue;
            }

            $char          = $this->_read();
            $this->_buffer = $char;
            $return        = $this->_readLine($char);

            if (0 === ($return & self::STATE_NO_ECHO)) {
                $output->writeAll($this->_buffer);
            }

            if (0 !== ($return & self::STATE_BREAK)) {
                break;
            }
        }

        return $this->getLine();
    }

    /**
     * Readline core.
     *
     * @param   string  $char    Char.
     * @return  string
     */
    public function _readLine($char)
    {
        if (isset($this->_mapping[$char]) &&
            is_callable($this->_mapping[$char])) {
            $mapping = $this->_mapping[$char];

            return $mapping($this);
        }

        if (isset($this->_mapping[$char])) {
            $this->_buffer = $this->_mapping[$char];
        } elseif (false === Ustring::isCharPrintable($char)) {
            Console\Cursor::bip();

            return static::STATE_CONTINUE | static::STATE_NO_ECHO;
        }

        if ($this->getLineLength() == $this->getLineCurrent()) {
            $this->appendLine($this->_buffer);

            return static::STATE_CONTINUE;
        }

        $this->insertLine($this->_buffer);
        $tail = mb_substr(
            $this->getLine(),
            $this->getLineCurrent() - 1
        );
        $this->_buffer = "\033[K" . $tail . str_repeat(
            "\033[D",
            mb_strlen($tail) - 1
        );

        return static::STATE_CONTINUE;
    }

    /**
     * Add mappings.
     *
     * @param   array  $mappings    Mappings.
     * @return  void
     */
    public function addMappings(array $mappings)
    {
        foreach ($mappings as $key => $mapping) {
            $this->addMapping($key, $mapping);
        }

        return;
    }

    /**
     * Add a mapping.
     * Supported key:
     *     • \e[… for \033[…;
     *     • \C-… for Ctrl-…;
     *     • abc for a simple mapping.
     * A mapping is a callable that has only one parameter of type
     * Hoa\Console\Readline and that returns a self::STATE_* constant.
     *
     * @param   string  $key        Key.
     * @param   mixed   $mapping    Mapping (a callable).
     * @return  void
     */
    public function addMapping($key, $mapping)
    {
        if ('\e[' === substr($key, 0, 3)) {
            $this->_mapping["\033[" . substr($key, 3)] = $mapping;
        } elseif ('\C-' === substr($key, 0, 3)) {
            $_key                       = ord(strtolower(substr($key, 3))) - 96;
            $this->_mapping[chr($_key)] = $mapping;
        } else {
            $this->_mapping[$key] = $mapping;
        }

        return;
    }

    /**
     * Add an entry in the history.
     *
     * @param   string  $line    Line.
     * @return  void
     */
    public function addHistory($line = null)
    {
        if (empty($line)) {
            return;
        }

        $this->_history[]      = $line;
        $this->_historyCurrent = $this->_historySize++;

        return;
    }

    /**
     * Clear history.
     *
     * @return  void
     */
    public function clearHistory()
    {
        unset($this->_history);
        $this->_history        = [];
        $this->_historyCurrent = 0;
        $this->_historySize    = 1;

        return;
    }

    /**
     * Get an entry in the history.
     *
     * @param   int  $i    Index of the entry.
     * @return  string
     */
    public function getHistory($i = null)
    {
        if (null === $i) {
            $i = $this->_historyCurrent;
        }

        if (!isset($this->_history[$i])) {
            return null;
        }

        return $this->_history[$i];
    }

    /**
     * Go backward in the history.
     *
     * @return  string
     */
    public function previousHistory()
    {
        if (0 >= $this->_historyCurrent) {
            return $this->getHistory(0);
        }

        return $this->getHistory($this->_historyCurrent--);
    }

    /**
     * Go forward in the history.
     *
     * @return  string
     */
    public function nextHistory()
    {
        if ($this->_historyCurrent + 1 >= $this->_historySize) {
            return $this->getLine();
        }

        return $this->getHistory(++$this->_historyCurrent);
    }

    /**
     * Get current line.
     *
     * @return  string
     */
    public function getLine()
    {
        return $this->_line;
    }

    /**
     * Append to current line.
     *
     * @param   string  $append    String to append.
     * @return  void
     */
    public function appendLine($append)
    {
        $this->_line       .= $append;
        $this->_lineLength  = mb_strlen($this->_line);
        $this->_lineCurrent = $this->_lineLength;

        return;
    }

    /**
     * Insert into current line at the current seek.
     *
     * @param   string  $insert    String to insert.
     * @return  void
     */
    public function insertLine($insert)
    {
        if ($this->_lineLength == $this->_lineCurrent) {
            return $this->appendLine($insert);
        }

        $this->_line         = mb_substr($this->_line, 0, $this->_lineCurrent) .
                               $insert .
                               mb_substr($this->_line, $this->_lineCurrent);
        $this->_lineLength   = mb_strlen($this->_line);
        $this->_lineCurrent += mb_strlen($insert);

        return;
    }

    /**
     * Reset current line.
     *
     * @return  void
     */
    protected function resetLine()
    {
        $this->_line        = null;
        $this->_lineCurrent = 0;
        $this->_lineLength  = 0;

        return;
    }

    /**
     * Get current line seek.
     *
     * @return  int
     */
    public function getLineCurrent()
    {
        return $this->_lineCurrent;
    }

    /**
     * Get current line length.
     *
     * @return  int
     */
    public function getLineLength()
    {
        return $this->_lineLength;
    }

    /**
     * Set prefix.
     *
     * @param   string  $prefix    Prefix.
     * @return  void
     */
    public function setPrefix($prefix)
    {
        $this->_prefix = $prefix;

        return;
    }

    /**
     * Get prefix.
     *
     * @return  string
     */
    public function getPrefix()
    {
        return $this->_prefix;
    }

    /**
     * Get buffer. Not for user.
     *
     * @return  string
     */
    public function getBuffer()
    {
        return $this->_buffer;
    }

    /**
     * Set an autocompleter.
     *
     * @param   \Hoa\Console\Readline\Autocompleter  $autocompleter    Auto-completer.
     * @return  \Hoa\Console\Readline\Autocompleter
     */
    public function setAutocompleter(Autocompleter $autocompleter)
    {
        $old                  = $this->_autocompleter;
        $this->_autocompleter = $autocompleter;

        return $old;
    }

    /**
     * Get the autocompleter.
     *
     * @return  \Hoa\Console\Readline\Autocompleter
     */
    public function getAutocompleter()
    {
        return $this->_autocompleter;
    }

    /**
     * Read on input. Not for user.
     *
     * @param   int  $length    Length.
     * @return  string
     */
    public function _read($length = 512)
    {
        return Console::getInput()->read($length);
    }

    /**
     * Set current line. Not for user.
     *
     * @param   string  $line    Line.
     * @return  void
     */
    public function setLine($line)
    {
        $this->_line        = $line;
        $this->_lineLength  = mb_strlen($this->_line);
        $this->_lineCurrent = $this->_lineLength;

        return;
    }

    /**
     * Set current line seek. Not for user.
     *
     * @param   int  $current    Seek.
     * @return  void
     */
    public function setLineCurrent($current)
    {
        $this->_lineCurrent = $current;

        return;
    }

    /**
     * Set line length. Not for user.
     *
     * @param   int  $length    Length.
     * @return  void
     */
    public function setLineLength($length)
    {
        $this->_lineLength = $length;

        return;
    }

    /**
     * Set buffer. Not for user.
     *
     * @param   string  $buffer    Buffer.
     * @return  string
     */
    public function setBuffer($buffer)
    {
        $this->_buffer = $buffer;

        return;
    }

    /**
     * Up arrow binding.
     * Go backward in the history.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindArrowUp(Readline $self)
    {
        if (0 === (static::STATE_CONTINUE & static::STATE_NO_ECHO)) {
            Console\Cursor::clear('↔');
            Console::getOutput()->writeAll($self->getPrefix());
        }
        $self->setBuffer($buffer = $self->previousHistory());
        $self->setLine($buffer);

        return static::STATE_CONTINUE;
    }

    /**
     * Down arrow binding.
     * Go forward in the history.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindArrowDown(Readline $self)
    {
        if (0 === (static::STATE_CONTINUE & static::STATE_NO_ECHO)) {
            Console\Cursor::clear('↔');
            Console::getOutput()->writeAll($self->getPrefix());
        }

        $self->setBuffer($buffer = $self->nextHistory());
        $self->setLine($buffer);

        return static::STATE_CONTINUE;
    }

    /**
     * Right arrow binding.
     * Move cursor to the right.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindArrowRight(Readline $self)
    {
        if ($self->getLineLength() > $self->getLineCurrent()) {
            if (0 === (static::STATE_CONTINUE & static::STATE_NO_ECHO)) {
                Console\Cursor::move('→');
            }

            $self->setLineCurrent($self->getLineCurrent() + 1);
        }

        $self->setBuffer(null);

        return static::STATE_CONTINUE;
    }

    /**
     * Left arrow binding.
     * Move cursor to the left.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindArrowLeft(Readline $self)
    {
        if (0 < $self->getLineCurrent()) {
            if (0 === (static::STATE_CONTINUE & static::STATE_NO_ECHO)) {
                Console\Cursor::move('←');
            }

            $self->setLineCurrent($self->getLineCurrent() - 1);
        }

        $self->setBuffer(null);

        return static::STATE_CONTINUE;
    }

    /**
     * Backspace and Control-H binding.
     * Delete the first character at the right of the cursor.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindBackspace(Readline $self)
    {
        $buffer = null;

        if (0 < $self->getLineCurrent()) {
            if (0 === (static::STATE_CONTINUE & static::STATE_NO_ECHO)) {
                Console\Cursor::move('←');
                Console\Cursor::clear('→');
            }

            if ($self->getLineLength() == $current = $self->getLineCurrent()) {
                $self->setLine(mb_substr($self->getLine(), 0, -1));
            } else {
                $line    = $self->getLine();
                $current = $self->getLineCurrent();
                $tail    = mb_substr($line, $current);
                $buffer  = $tail . str_repeat("\033[D", mb_strlen($tail));
                $self->setLine(mb_substr($line, 0, $current - 1) . $tail);
                $self->setLineCurrent($current - 1);
            }
        }

        $self->setBuffer($buffer);

        return static::STATE_CONTINUE;
    }

    /**
     * Control-A binding.
     * Move cursor to beginning of line.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindControlA(Readline $self)
    {
        for ($i = $self->getLineCurrent() - 1; 0 <= $i; --$i) {
            $self->_bindArrowLeft($self);
        }

        return static::STATE_CONTINUE;
    }

    /**
     * Control-B binding.
     * Move cursor backward one word.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindControlB(Readline $self)
    {
        $current = $self->getLineCurrent();

        if (0 === $current) {
            return static::STATE_CONTINUE;
        }

        $words = preg_split(
            '#\b#u',
            $self->getLine(),
            -1,
            PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        for (
            $i = 0, $max = count($words) - 1;
            $i < $max && $words[$i + 1][1] < $current;
            ++$i
        );

        for ($j = $words[$i][1] + 1; $current >= $j; ++$j) {
            $self->_bindArrowLeft($self);
        }

        return static::STATE_CONTINUE;
    }

    /**
     * Control-E binding.
     * Move cursor to end of line.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindControlE(Readline $self)
    {
        for (
            $i = $self->getLineCurrent(), $max = $self->getLineLength();
            $i < $max;
            ++$i
        ) {
            $self->_bindArrowRight($self);
        }

        return static::STATE_CONTINUE;
    }

    /**
     * Control-F binding.
     * Move cursor forward one word.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindControlF(Readline $self)
    {
        $current = $self->getLineCurrent();

        if ($self->getLineLength() === $current) {
            return static::STATE_CONTINUE;
        }

        $words = preg_split(
            '#\b#u',
            $self->getLine(),
            -1,
            PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        for (
            $i = 0, $max = count($words) - 1;
            $i < $max && $words[$i][1] < $current;
            ++$i
        );

        if (!isset($words[$i + 1])) {
            $words[$i + 1] = [1 => $self->getLineLength()];
        }

        for ($j = $words[$i + 1][1]; $j > $current; --$j) {
            $self->_bindArrowRight($self);
        }

        return static::STATE_CONTINUE;
    }

    /**
     * Control-W binding.
     * Delete first backward word.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindControlW(Readline $self)
    {
        $current = $self->getLineCurrent();

        if (0 === $current) {
            return static::STATE_CONTINUE;
        }

        $words = preg_split(
            '#\b#u',
            $self->getLine(),
            -1,
            PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        for (
            $i = 0, $max = count($words) - 1;
            $i < $max && $words[$i + 1][1] < $current;
            ++$i
        );

        for ($j = $words[$i][1] + 1; $current >= $j; ++$j) {
            $self->_bindBackspace($self);
        }

        return static::STATE_CONTINUE;
    }

    /**
     * Newline binding.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindNewline(Readline $self)
    {
        $self->addHistory($self->getLine());

        return static::STATE_BREAK;
    }

    /**
     * Tab binding.
     *
     * @param   \Hoa\Console\Readline  $self    Self.
     * @return  int
     */
    public function _bindTab(Readline $self)
    {
        $output        = Console::getOutput();
        $autocompleter = $self->getAutocompleter();
        $state         = static::STATE_CONTINUE | static::STATE_NO_ECHO;

        if (null === $autocompleter) {
            return $state;
        }

        $current = $self->getLineCurrent();
        $line    = $self->getLine();

        if (0 === $current) {
            return $state;
        }

        $matches = preg_match_all(
            '#' . $autocompleter->getWordDefinition() . '$#u',
            mb_substr($line, 0, $current),
            $words
        );

        if (0 === $matches) {
            return $state;
        }

        $word = $words[0][0];

        if ('' === trim($word)) {
            return $state;
        }

        $solution = $autocompleter->complete($word);
        $length   = mb_strlen($word);

        if (null === $solution) {
            return $state;
        }

        if (is_array($solution)) {
            $_solution = $solution;
            $count     = count($_solution) - 1;
            $cWidth    = 0;
            $window    = Console\Window::getSize();
            $wWidth    = $window['x'];
            $cursor    = Console\Cursor::getPosition();

            array_walk($_solution, function (&$value) use (&$cWidth) {
                $handle = mb_strlen($value);

                if ($handle > $cWidth) {
                    $cWidth = $handle;
                }

                return;
            });
            array_walk($_solution, function (&$value) use (&$cWidth) {
                $handle = mb_strlen($value);

                if ($handle >= $cWidth) {
                    return;
                }

                $value .= str_repeat(' ', $cWidth - $handle);

                return;
            });

            $mColumns = (int) floor($wWidth / ($cWidth + 2));
            $mLines   = (int) ceil(($count + 1) / $mColumns);
            --$mColumns;
            $i        = 0;

            if (0 > $window['y'] - $cursor['y'] - $mLines) {
                Console\Window::scroll('↑', $mLines);
                Console\Cursor::move('↑', $mLines);
            }

            Console\Cursor::save();
            Console\Cursor::hide();
            Console\Cursor::move('↓ LEFT');
            Console\Cursor::clear('↓');

            foreach ($_solution as $j => $s) {
                $output->writeAll("\033[0m" . $s . "\033[0m");

                if ($i++ < $mColumns) {
                    $output->writeAll('  ');
                } else {
                    $i = 0;

                    if (isset($_solution[$j + 1])) {
                        $output->writeAll("\n");
                    }
                }
            }

            Console\Cursor::restore();
            Console\Cursor::show();

            ++$mColumns;
            $input    = Console::getInput();
            $read     = [$input->getStream()->getStream()];
            $mColumn  = -1;
            $mLine    = -1;
            $coord    = -1;
            $unselect = function () use (
                &$mColumn,
                &$mLine,
                &$coord,
                &$_solution,
                &$cWidth,
                $output
            ) {
                Console\Cursor::save();
                Console\Cursor::hide();
                Console\Cursor::move('↓ LEFT');
                Console\Cursor::move('→', $mColumn * ($cWidth + 2));
                Console\Cursor::move('↓', $mLine);
                $output->writeAll("\033[0m" . $_solution[$coord] . "\033[0m");
                Console\Cursor::restore();
                Console\Cursor::show();

                return;
            };
            $select = function () use (
                &$mColumn,
                &$mLine,
                &$coord,
                &$_solution,
                &$cWidth,
                $output
            ) {
                Console\Cursor::save();
                Console\Cursor::hide();
                Console\Cursor::move('↓ LEFT');
                Console\Cursor::move('→', $mColumn * ($cWidth + 2));
                Console\Cursor::move('↓', $mLine);
                $output->writeAll("\033[7m" . $_solution[$coord] . "\033[0m");
                Console\Cursor::restore();
                Console\Cursor::show();

                return;
            };
            $init = function () use (
                &$mColumn,
                &$mLine,
                &$coord,
                &$select
            ) {
                $mColumn = 0;
                $mLine   = 0;
                $coord   = 0;
                $select();

                return;
            };

            while (true) {
                @stream_select($read, $write, $except, 30, 0);

                if (empty($read)) {
                    $read = [$input->getStream()->getStream()];

                    continue;
                }

                switch ($char = $self->_read()) {
                    case "\033[A":
                        if (-1 === $mColumn && -1 === $mLine) {
                            $init();

                            break;
                        }

                        $unselect();
                        $coord   = max(0, $coord - $mColumns);
                        $mLine   = (int) floor($coord / $mColumns);
                        $mColumn = $coord % $mColumns;
                        $select();

                        break;

                    case "\033[B":
                        if (-1 === $mColumn && -1 === $mLine) {
                            $init();

                            break;
                        }

                        $unselect();
                        $coord   = min($count, $coord + $mColumns);
                        $mLine   = (int) floor($coord / $mColumns);
                        $mColumn = $coord % $mColumns;
                        $select();

                        break;

                    case "\t":
                    case "\033[C":
                        if (-1 === $mColumn && -1 === $mLine) {
                            $init();

                            break;
                        }

                        $unselect();
                        $coord   = min($count, $coord + 1);
                        $mLine   = (int) floor($coord / $mColumns);
                        $mColumn = $coord % $mColumns;
                        $select();

                        break;

                    case "\033[D":
                        if (-1 === $mColumn && -1 === $mLine) {
                            $init();

                            break;
                        }

                        $unselect();
                        $coord   = max(0, $coord - 1);
                        $mLine   = (int) floor($coord / $mColumns);
                        $mColumn = $coord % $mColumns;
                        $select();

                        break;

                    case "\n":
                        if (-1 !== $mColumn && -1 !== $mLine) {
                            $tail     = mb_substr($line, $current);
                            $current -= $length;
                            $self->setLine(
                                mb_substr($line, 0, $current) .
                                $solution[$coord] .
                                $tail
                            );
                            $self->setLineCurrent(
                                $current + mb_strlen($solution[$coord])
                            );

                            Console\Cursor::move('←', $length);
                            $output->writeAll($solution[$coord]);
                            Console\Cursor::clear('→');
                            $output->writeAll($tail);
                            Console\Cursor::move('←', mb_strlen($tail));
                        }

                    default:
                        $mColumn = -1;
                        $mLine   = -1;
                        $coord   = -1;
                        Console\Cursor::save();
                        Console\Cursor::move('↓ LEFT');
                        Console\Cursor::clear('↓');
                        Console\Cursor::restore();

                        if ("\033" !== $char && "\n" !== $char) {
                            $self->setBuffer($char);

                            return $self->_readLine($char);
                        }

                        break 2;
                }
            }

            return $state;
        }

        $tail     = mb_substr($line, $current);
        $current -= $length;
        $self->setLine(
            mb_substr($line, 0, $current) .
            $solution .
            $tail
        );
        $self->setLineCurrent(
            $current + mb_strlen($solution)
        );

        Console\Cursor::move('←', $length);
        $output->writeAll($solution);
        Console\Cursor::clear('→');
        $output->writeAll($tail);
        Console\Cursor::move('←', mb_strlen($tail));

        return $state;
    }
}

/**
 * Advanced interaction.
 */
Console::advancedInteraction();

/**
 * Flex entity.
 */
Consistency::flexEntity('Hoa\Console\Readline\Readline');
