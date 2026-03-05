<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive;

use Psy\Readline\Interactive\Input\Buffer;

/**
 * Helpers for defining and asserting buffer state with a tagged string.
 *
 * Use `<cursor>` to mark cursor position:
 *
 *     [$text, $cursor] = self::parseBuffer('echo <cursor>');
 *     // ['echo ', 5]
 *
 *     [$text, $cursor] = self::parseBuffer('echo "<cursor>"');
 *     // ['echo ""', 6]
 */
trait BufferAssertionTrait
{
    /**
     * Parse a tagged buffer string into text and cursor position.
     *
     * @return array{string, int}
     */
    public static function parseBuffer(string $spec): array
    {
        $tag = '<cursor>';
        $pos = \mb_strpos($spec, $tag);
        \assert($pos !== false, 'Buffer spec must contain <cursor> tag');

        $text = \mb_substr($spec, 0, $pos).\mb_substr($spec, $pos + \mb_strlen($tag));

        return [$text, $pos];
    }

    /**
     * Set a buffer's text and cursor from a tagged string.
     */
    private function setBufferState(Buffer $buffer, string $spec): void
    {
        [$text, $cursor] = self::parseBuffer($spec);
        $buffer->setText($text);
        $buffer->setCursor($cursor);
    }

    /**
     * Assert that a buffer's text and cursor match a tagged string.
     */
    private function assertBufferState(string $spec, Buffer $buffer, string $message = ''): void
    {
        [$text, $cursor] = self::parseBuffer($spec);
        $this->assertEquals($text, $buffer->getText(), $message ?: 'Buffer text mismatch');
        $this->assertEquals($cursor, $buffer->getCursor(), $message ?: 'Buffer cursor mismatch');
    }
}
