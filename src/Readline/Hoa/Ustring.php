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

namespace Psy\Readline\Hoa;

/**
 * This class represents a UTF-8 string.
 * Please, see:
 *   * http://www.ietf.org/rfc/rfc3454.txt,
 *   * http://unicode.org/reports/tr9/,
 *   * http://www.unicode.org/Public/6.0.0/ucd/UnicodeData.txt.
 */
class Ustring
{
    /**
     * Check if ext/mbstring is available.
     */
    public static function checkMbString(): bool
    {
        return function_exists('mb_substr');
    }

    /**
     * Get the number of column positions of a wide-character.
     *
     * This is a PHP implementation of wcwidth() and wcswidth() (defined in IEEE
     * Std 1002.1-2001) for Unicode, by Markus Kuhn. Please, see
     * http://www.cl.cam.ac.uk/~mgk25/ucs/wcwidth.c.
     *
     * The wcwidth(wc) function shall either return 0 (if wc is a null
     * wide-character code), or return the number of column positions to be
     * occupied by the wide-character code wc, or return -1 (if wc does not
     * correspond to a printable wide-character code).
     */
    public static function getCharWidth(string $char): int
    {
        $char = (string) $char;
        $c    = static::toCode($char);

        // Test for 8-bit control characters.
        if (0x0 === $c) {
            return 0;
        }

        if (0x20 > $c || (0x7f <= $c && $c < 0xa0)) {
            return -1;
        }

        // Non-spacing characters.
        if (0xad !== $c &&
            0 !== preg_match('#^[\p{Mn}\p{Me}\p{Cf}\x{1160}-\x{11ff}\x{200b}]#u', $char)) {
            return 0;
        }

        // If we arrive here, $c is not a combining C0/C1 control character.
        return 1 +
            (0x1100 <= $c &&
                (0x115f >= $c ||                        // Hangul Jamo init. consonants
                 0x2329 === $c || 0x232a === $c ||
                     (0x2e80 <= $c && 0xa4cf >= $c &&
                      0x303f !== $c) ||                // CJK…Yi
                     (0xac00 <= $c && 0xd7a3 >= $c) || // Hangul Syllables
                     (0xf900 <= $c && 0xfaff >= $c) || // CJK Compatibility Ideographs
                     (0xfe10 <= $c && 0xfe19 >= $c) || // Vertical forms
                     (0xfe30 <= $c && 0xfe6f >= $c) || // CJK Compatibility Forms
                     (0xff00 <= $c && 0xff60 >= $c) || // Fullwidth Forms
                     (0xffe0 <= $c && 0xffe6 >= $c) ||
                     (0x20000 <= $c && 0x2fffd >= $c) ||
                     (0x30000 <= $c && 0x3fffd >= $c)));
    }

    /**
     * Check whether the character is printable or not.
     */
    public static function isCharPrintable(string $char): bool
    {
        return 1 <= static::getCharWidth($char);
    }

    /**
     * Get a decimal code representation of a specific character.
     */
    public static function toCode(string $char): int
    {
        $char  = (string) $char;
        $code  = ord($char[0]);
        $bytes = 1;

        if (!($code & 0x80)) { // 0xxxxxxx
            return $code;
        }

        if (($code & 0xe0) === 0xc0) { // 110xxxxx
            $bytes = 2;
            $code  = $code & ~0xc0;
        } elseif (($code & 0xf0) == 0xe0) { // 1110xxxx
            $bytes = 3;
            $code  = $code & ~0xe0;
        } elseif (($code & 0xf8) === 0xf0) { // 11110xxx
            $bytes = 4;
            $code  = $code & ~0xf0;
        }

        for ($i = 2; $i <= $bytes; $i++) { // 10xxxxxx
            $code = ($code << 6) + (ord($char[$i - 1]) & ~0x80);
        }

        return $code;
    }
}
