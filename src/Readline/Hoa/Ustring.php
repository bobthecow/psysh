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

use ArrayIterator;
use Collator;
use Transliterator;

/**
 * This class represents a UTF-8 string.
 * Please, see:
 *   * http://www.ietf.org/rfc/rfc3454.txt,
 *   * http://unicode.org/reports/tr9/,
 *   * http://www.unicode.org/Public/6.0.0/ucd/UnicodeData.txt.
 */
class Ustring implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Left-To-Right.
     */
    const LTR              = 0;

    /**
     * Right-To-Left.
     */
    const RTL              = 1;

    /**
     * ZERO WIDTH NON-BREAKING SPACE (ZWNPBSP, aka byte-order mark, BOM).
     */
    const BOM              = 0xfeff;

    /**
     * LEFT-TO-RIGHT MARK.
     */
    const LRM              = 0x200e;

    /**
     * RIGHT-TO-LEFT MARK.
     */
    const RLM              = 0x200f;

    /**
     * LEFT-TO-RIGHT EMBEDDING.
     */
    const LRE              = 0x202a;

    /**
     * RIGHT-TO-LEFT EMBEDDING.
     */
    const RLE              = 0x202b;

    /**
     * POP DIRECTIONAL FORMATTING.
     */
    const PDF              = 0x202c;

    /**
     * LEFT-TO-RIGHT OVERRIDE.
     */
    const LRO              = 0x202d;

    /**
     * RIGHT-TO-LEFT OVERRIDE.
     */
    const RLO              = 0x202e;

    /**
     * Represent the beginning of the string.
     */
    const BEGINNING        = 1;

    /**
     * Represent the end of the string.
     */
    const END              = 2;

    /**
     * Split: non-empty pieces is returned.
     */
    const WITHOUT_EMPTY    = PREG_SPLIT_NO_EMPTY;

    /**
     * Split: parenthesized expression in the delimiter pattern will be captured
     * and returned.
     */
    const WITH_DELIMITERS  = PREG_SPLIT_DELIM_CAPTURE;

    /**
     * Split: offsets of captures will be returned.
     */
    const WITH_OFFSET      = PREG_OFFSET_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE;

    /**
     * Group results by patterns.
     */
    const GROUP_BY_PATTERN = PREG_PATTERN_ORDER;

    /**
     * Group results by tuple (set of patterns).
     */
    const GROUP_BY_TUPLE   = PREG_SET_ORDER;

    /**
     * Current string.
     */
    protected $_string          = null;

    /**
     * Direction. Please see self::LTR and self::RTL constants.
     */
    protected $_direction       = null;

    /**
     * Collator.
     */
    protected static $_collator = null;



    /**
     * Construct a UTF-8 string.
     */
    public function __construct(string $string = null)
    {
        if (null !== $string) {
            $this->append($string);
        }

        return;
    }

    /**
     * Check if ext/mbstring is available.
     */
    public static function checkMbString(): bool
    {
        return function_exists('mb_substr');
    }

    /**
     * Check if ext/iconv is available.
     */
    public static function checkIconv(): bool
    {
        return function_exists('iconv');
    }

    /**
     * Append a substring to the current string, i.e. add to the end.
     */
    public function append(string $substring): self
    {
        $this->_string .= $substring;

        return $this;
    }

    /**
     * Prepend a substring to the current string, i.e. add to the start.
     */
    public function prepend(string $substring): self
    {
        $this->_string = $substring . $this->_string;

        return $this;
    }

    /**
     * Pad the current string to a certain length with another piece, aka piece.
     */
    public function pad(int $length, string $piece, int $side = self::END): self
    {
        $difference = $length - $this->count();

        if (0 >= $difference) {
            return $this;
        }

        $handle = null;

        for ($i = $difference / mb_strlen($piece) - 1; $i >= 0; --$i) {
            $handle .= $piece;
        }

        $handle .= mb_substr($piece, 0, $difference - mb_strlen($handle));

        return
            static::END === $side
                ? $this->append($handle)
                : $this->prepend($handle);
    }

    /**
     * Make a comparison with a string.
     * Return < 0 if current string is less than $string, > 0 if greater and 0
     * if equal.
     */
    public function compare($string): int
    {
        if (null === $collator = static::getCollator()) {
            return strcmp($this->_string, (string) $string);
        }

        return $collator->compare($this->_string, $string);
    }

    /**
     * Get collator.
     */
    public static function getCollator()
    {
        if (false === class_exists('Collator')) {
            return null;
        }

        if (null === static::$_collator) {
            static::$_collator = new Collator(setlocale(LC_COLLATE, null));
        }

        return static::$_collator;
    }

    /**
     * Ensure that the pattern is safe for Unicode: add the “u” option.
     */
    public static function safePattern(string $pattern): string
    {
        $delimiter = mb_substr($pattern, 0, 1);
        $options   = mb_substr(
            mb_strrchr($pattern, $delimiter, false),
            mb_strlen($delimiter)
        );

        if (false === strpos($options, 'u')) {
            $pattern .= 'u';
        }

        return $pattern;
    }

    /**
     * Perform a regular expression (PCRE) match.
     */
    public function match(
        string $pattern,
        array &$matches = null,
        int $flags      = 0,
        int $offset     = 0,
        bool $global    = false
    ): int {
        $pattern = static::safePattern($pattern);

        if (0 === $flags) {
            if (true === $global) {
                $flags = static::GROUP_BY_PATTERN;
            }
        } else {
            $flags &= ~PREG_SPLIT_OFFSET_CAPTURE;
        }


        $offset = strlen(mb_substr($this->_string, 0, $offset));

        if (true === $global) {
            return preg_match_all(
                $pattern,
                $this->_string,
                $matches,
                $flags,
                $offset
            );
        }

        return preg_match($pattern, $this->_string, $matches, $flags, $offset);
    }

    /**
     * Perform a regular expression (PCRE) search and replace.
     */
    public function replace($pattern, $replacement, int $limit = -1): self
    {
        $pattern = static::safePattern($pattern);

        if (false === is_callable($replacement)) {
            $this->_string = preg_replace(
                $pattern,
                $replacement,
                $this->_string,
                $limit
            );
        } else {
            $this->_string = preg_replace_callback(
                $pattern,
                $replacement,
                $this->_string,
                $limit
            );
        }

        return $this;
    }

    /**
     * Split the current string according to a given pattern (PCRE).
     */
    public function split(
        string $pattern,
        int $limit = -1,
        int $flags = self::WITHOUT_EMPTY
    ): array {
        return preg_split(
            static::safePattern($pattern),
            $this->_string,
            $limit,
            $flags
        );
    }

    /**
     * Iterator over chars.
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(preg_split('#(?<!^)(?!$)#u', $this->_string));
    }

    /**
     * Perform a lowercase folding on the current string.
     */
    public function toLowerCase(): self
    {
        $this->_string = mb_strtolower($this->_string);

        return $this;
    }

    /**
     * Perform an uppercase folding on the current string.
     */
    public function toUpperCase(): self
    {
        $this->_string = mb_strtoupper($this->_string);

        return $this;
    }

    /**
     * Transform a UTF-8 string into an ASCII one.
     * First, try with a transliterator. If not available, will fallback to a
     * normalizer. If not available, will try something homemade.
     */
    public function toAscii(bool $try = false): self
    {
        if (0 === preg_match('#[\x80-\xff]#', $this->_string)) {
            return $this;
        }

        $string  = $this->_string;
        $transId =
            'Any-Latin; ' .
            '[\p{S}] Name; ' .
            'Latin-ASCII';

        if (null !== $transliterator = static::getTransliterator($transId)) {
            $this->_string = preg_replace_callback(
                '#\\\N\{([A-Z ]+)\}#u',
                function (array $matches) {
                    return '(' . strtolower($matches[1]) . ')';
                },
                $transliterator->transliterate($string)
            );

            return $this;
        }

        if (false === class_exists('Normalizer')) {
            if (false === $try) {
                throw new UstringException(
                    '%s needs the class Normalizer to work properly, ' .
                    'or you can force a try by using %1$s(true).',
                    0,
                    __METHOD__
                );
            }

            $string        = static::transcode($string, 'UTF-8', 'ASCII//IGNORE//TRANSLIT');
            $this->_string = preg_replace('#(?:[\'"`^](\w))#u', '\1', $string);

            return $this;
        }

        $string        = \Normalizer::normalize($string, \Normalizer::NFKD);
        $string        = preg_replace('#\p{Mn}+#u', '', $string);
        $this->_string = static::transcode($string, 'UTF-8', 'ASCII//IGNORE//TRANSLIT');

        return $this;
    }

    /**
     * Transliterate the string into another.
     * See self::getTransliterator for more information.
     */
    public function transliterate(string $identifier, int $start = 0, int $end = null): self
    {
        if (null === $transliterator = static::getTransliterator($identifier)) {
            throw new UstringException(
                '%s needs the class Transliterator to work properly.',
                1,
                __METHOD__
            );
        }

        $this->_string = $transliterator->transliterate($this->_string, $start, $end);

        return $this;
    }

    /**
     * Get transliterator.
     * See http://userguide.icu-project.org/transforms/general for $identifier.
     */
    public static function getTransliterator(string $identifier)
    {
        if (false === class_exists('Transliterator')) {
            return null;
        }

        return Transliterator::create($identifier);
    }

    /**
     * Strip characters (default \s) of the current string.
     */
    public function trim(string $regex = '\s', int $side = self::BEGINNING | self::END): self
    {
        $regex  = '(?:' . $regex . ')+';
        $handle = null;

        if (0 !== ($side & static::BEGINNING)) {
            $handle .= '(^' . $regex . ')';
        }

        if (0 !== ($side & static::END)) {
            if (null !== $handle) {
                $handle .= '|';
            }

            $handle .= '(' . $regex . '$)';
        }

        $this->_string    = preg_replace('#' . $handle . '#u', '', $this->_string);
        $this->_direction = null;

        return $this;
    }

    /**
     * Compute offset (negative, unbound etc.).
     */
    protected function computeOffset(int $offset): int
    {
        $length = mb_strlen($this->_string);

        if (0 > $offset) {
            $offset = -$offset % $length;

            if (0 !== $offset) {
                $offset = $length - $offset;
            }
        } elseif ($offset >= $length) {
            $offset %= $length;
        }

        return $offset;
    }

    /**
     * Get a specific chars of the current string.
     */
    public function offsetGet($offset): string
    {
        return mb_substr($this->_string, $this->computeOffset($offset), 1);
    }

    /**
     * Set a specific character of the current string.
     */
    public function offsetSet($offset, $value): void
    {
        $head   = null;
        $offset = $this->computeOffset($offset);

        if (0 < $offset) {
            $head = mb_substr($this->_string, 0, $offset);
        }

        $tail             = mb_substr($this->_string, $offset + 1);
        $this->_string    = $head . $value . $tail;
        $this->_direction = null;
    }

    /**
     * Delete a specific character of the current string.
     */
    public function offsetUnset($offset): void
    {
        $this->offsetSet($offset, null);
    }

    /**
     * Check if a specific offset exists.
     */
    public function offsetExists($offset): bool
    {
        return true;
    }

    /**
     * Reduce the strings.
     */
    public function reduce(int $start, int $length = null): self
    {
        $this->_string = mb_substr($this->_string, $start, $length);

        return $this;
    }

    /**
     * Count number of characters of the current string.
     */
    public function count(): int
    {
        return mb_strlen($this->_string);
    }

    /**
     * Get byte (not character) at a specific offset.
     */
    public function getByteAt(int $offset): string
    {
        $length = strlen($this->_string);

        if (0 > $offset) {
            $offset = -$offset % $length;

            if (0 !== $offset) {
                $offset = $length - $offset;
            }
        } elseif ($offset >= $length) {
            $offset %= $length;
        }

        return $this->_string[$offset];
    }

    /**
     * Count number of bytes (not characters) of the current string.
     */
    public function getBytesLength(): int
    {
        return strlen($this->_string);
    }

    /**
     * Get the width of the current string.
     * Useful when printing the string in monotype (some character need more
     * than one column to be printed).
     */
    public function getWidth(): int
    {
        return mb_strwidth($this->_string);
    }

    /**
     * Get direction of the current string.
     * Please, see the self::LTR and self::RTL constants.
     * It does not yet support embedding directions.
     */
    public function getDirection(): int
    {
        if (null === $this->_direction) {
            if (null === $this->_string) {
                $this->_direction = static::LTR;
            } else {
                $this->_direction = static::getCharDirection(
                    mb_substr($this->_string, 0, 1)
                );
            }
        }

        return $this->_direction;
    }

    /**
     * Get character of a specific character.
     * Please, see the self::LTR and self::RTL constants.
     */
    public static function getCharDirection(string $char): int
    {
        $c = static::toCode($char);

        if (!(0x5be <= $c && 0x10b7f >= $c)) {
            return static::LTR;
        }

        if (0x85e >= $c) {
            if (0x5be === $c ||
                0x5c0 === $c ||
                0x5c3 === $c ||
                0x5c6 === $c ||
                (0x5d0 <= $c && 0x5ea >= $c) ||
                (0x5f0 <= $c && 0x5f4 >= $c) ||
                0x608 === $c ||
                0x60b === $c ||
                0x60d === $c ||
                0x61b === $c ||
                (0x61e <= $c && 0x64a >= $c) ||
                (0x66d <= $c && 0x66f >= $c) ||
                (0x671 <= $c && 0x6d5 >= $c) ||
                (0x6e5 <= $c && 0x6e6 >= $c) ||
                (0x6ee <= $c && 0x6ef >= $c) ||
                (0x6fa <= $c && 0x70d >= $c) ||
                0x710 === $c ||
                (0x712 <= $c && 0x72f >= $c) ||
                (0x74d <= $c && 0x7a5 >= $c) ||
                0x7b1 === $c ||
                (0x7c0 <= $c && 0x7ea >= $c) ||
                (0x7f4 <= $c && 0x7f5 >= $c) ||
                0x7fa === $c ||
                (0x800 <= $c && 0x815 >= $c) ||
                0x81a === $c ||
                0x824 === $c ||
                0x828 === $c ||
                (0x830 <= $c && 0x83e >= $c) ||
                (0x840 <= $c && 0x858 >= $c) ||
                0x85e === $c) {
                return static::RTL;
            }
        } elseif (0x200f === $c) {
            return static::RTL;
        } elseif (0xfb1d <= $c) {
            if (0xfb1d === $c ||
                (0xfb1f <= $c && 0xfb28 >= $c) ||
                (0xfb2a <= $c && 0xfb36 >= $c) ||
                (0xfb38 <= $c && 0xfb3c >= $c) ||
                0xfb3e === $c ||
                (0xfb40 <= $c && 0xfb41 >= $c) ||
                (0xfb43 <= $c && 0xfb44 >= $c) ||
                (0xfb46 <= $c && 0xfbc1 >= $c) ||
                (0xfbd3 <= $c && 0xfd3d >= $c) ||
                (0xfd50 <= $c && 0xfd8f >= $c) ||
                (0xfd92 <= $c && 0xfdc7 >= $c) ||
                (0xfdf0 <= $c && 0xfdfc >= $c) ||
                (0xfe70 <= $c && 0xfe74 >= $c) ||
                (0xfe76 <= $c && 0xfefc >= $c) ||
                (0x10800 <= $c && 0x10805 >= $c) ||
                0x10808 === $c ||
                (0x1080a <= $c && 0x10835 >= $c) ||
                (0x10837 <= $c && 0x10838 >= $c) ||
                0x1083c === $c ||
                (0x1083f <= $c && 0x10855 >= $c) ||
                (0x10857 <= $c && 0x1085f >= $c) ||
                (0x10900 <= $c && 0x1091b >= $c) ||
                (0x10920 <= $c && 0x10939 >= $c) ||
                0x1093f === $c ||
                0x10a00 === $c ||
                (0x10a10 <= $c && 0x10a13 >= $c) ||
                (0x10a15 <= $c && 0x10a17 >= $c) ||
                (0x10a19 <= $c && 0x10a33 >= $c) ||
                (0x10a40 <= $c && 0x10a47 >= $c) ||
                (0x10a50 <= $c && 0x10a58 >= $c) ||
                (0x10a60 <= $c && 0x10a7f >= $c) ||
                (0x10b00 <= $c && 0x10b35 >= $c) ||
                (0x10b40 <= $c && 0x10b55 >= $c) ||
                (0x10b58 <= $c && 0x10b72 >= $c) ||
                (0x10b78 <= $c && 0x10b7f >= $c)) {
                return static::RTL;
            }
        }

        return static::LTR;
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
     * Get a UTF-8 character from its decimal code representation.
     */
    public static function fromCode(int $code): string
    {
        return mb_convert_encoding(
            '&#x' . dechex($code) . ';',
            'UTF-8',
            'HTML-ENTITIES'
        );
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

    /**
     * Get a binary representation of a specific character.
     */
    public static function toBinaryCode(string $char): string
    {
        $char = (string) $char;
        $out  = '';

        for ($i = 0, $max = strlen($char); $i < $max; ++$i) {
            $out .= vsprintf('%08b', ord($char[$i]));
        }

        return $out;
    }

    /**
     * Transcode.
     */
    public static function transcode(string $string, string $from, string $to = 'UTF-8'): string
    {
        if (false === static::checkIconv()) {
            throw new UstringException(
                '%s needs the iconv extension.',
                2,
                __CLASS__
            );
        }

        return iconv($from, $to, $string);
    }

    /**
     * Check if a string is encoded in UTF-8.
     */
    public static function isUtf8(string $string): bool
    {
        return (bool) preg_match('##u', $string);
    }

    /**
     * Copy current object string
     */
    public function copy(): self
    {
        return clone $this;
    }

    /**
     * Transform the object as a string.
     */
    public function __toString(): string
    {
        return $this->_string;
    }
}
