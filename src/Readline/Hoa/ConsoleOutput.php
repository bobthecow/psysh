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

namespace Hoa\Console;

use Hoa\Stream;

/**
 * Class \Hoa\Console\Output.
 *
 * This class represents the output of a program. Most of the time, this is
 * going to be STDOUT.
 *
 * @copyright  Copyright © 2007-2017 Hoa community
 * @license    New BSD License
 */
class Output implements Stream\IStream\Out
{
    /**
     * Whether the multiplexer must be considered while writing on the output.
     *
     * @var bool
     */
    protected $_considerMultiplexer = false;

    /**
     * Real output stream.
     *
     * @var \Hoa\Stream\IStream\Out
     */
    protected $_output              = null;



    /**
     * Wraps an `Hoa\Stream\IStream\Out` stream.
     *
     * @param  \Hoa\Stream\IStream\Out  $output    Output.
     */
    public function __construct(Stream\IStream\Out $output = null)
    {
        $this->_output = $output;

        return;
    }

    /**
     * Get the real output stream.
     *
     * @return  \Hoa\Stream\IStream\Out
     */
    public function getStream()
    {
        return $this->_output;
    }

    /**
     * Write n characters.
     *
     * @param   string  $string    String.
     * @param   int     $length    Length.
     * @return  void
     * @throws  \Hoa\Console\Exception
     */
    public function write($string, $length)
    {
        if (0 > $length) {
            throw new Exception(
                'Length must be greater than 0, given %d.',
                0,
                $length
            );
        }

        $out = substr($string, 0, $length);

        if (true === $this->isMultiplexerConsidered()) {
            if (true === Console::isTmuxRunning()) {
                $out =
                    "\033Ptmux;" .
                    str_replace("\033", "\033\033", $out) .
                    "\033\\";
            }

            $length = strlen($out);
        }

        if (null === $this->_output) {
            echo $out;
        } else {
            $this->_output->write($out, $length);
        }
    }

    /**
     * Write a string.
     *
     * @param   string  $string    String.
     * @return  void
     */
    public function writeString($string)
    {
        $string = (string) $string;

        return $this->write($string, strlen($string));
    }

    /**
     * Write a character.
     *
     * @param   string  $character    Character.
     * @return  void
     */
    public function writeCharacter($character)
    {
        return $this->write((string) $character[0], 1);
    }

    /**
     * Write a boolean.
     *
     * @param   bool  $boolean    Boolean.
     * @return  void
     */
    public function writeBoolean($boolean)
    {
        return $this->write(((bool) $boolean) ? '1' : '0', 1);
    }

    /**
     * Write an integer.
     *
     * @param   int  $integer    Integer.
     * @return  void
     */
    public function writeInteger($integer)
    {
        $integer = (string) (int) $integer;

        return $this->write($integer, strlen($integer));
    }

    /**
     * Write a float.
     *
     * @param   float  $float    Float.
     * @return  void
     */
    public function writeFloat($float)
    {
        $float = (string) (float) $float;

        return $this->write($float, strlen($float));
    }

    /**
     * Write an array.
     *
     * @param   array  $array    Array.
     * @return  void
     */
    public function writeArray(array $array)
    {
        $array = var_export($array, true);

        return $this->write($array, strlen($array));
    }

    /**
     * Write a line.
     *
     * @param   string  $line    Line.
     * @return  void
     */
    public function writeLine($line)
    {
        if (false === $n = strpos($line, "\n")) {
            return $this->write($line . "\n", strlen($line) + 1);
        }

        ++$n;

        return $this->write(substr($line, 0, $n), $n);
    }

    /**
     * Write all, i.e. as much as possible.
     *
     * @param   string  $string    String.
     * @return  void
     */
    public function writeAll($string)
    {
        return $this->write($string, strlen($string));
    }

    /**
     * Truncate a stream to a given length.
     *
     * @param   int  $size    Size.
     * @return  bool
     */
    public function truncate($size)
    {
        return false;
    }

    /**
     * Consider the multiplexer (if running) while writing on the output.
     *
     * @param   bool  $consider    Consider the multiplexer or not.
     * @return  bool
     */
    public function considerMultiplexer($consider)
    {
        $old                        = $this->_considerMultiplexer;
        $this->_considerMultiplexer = $consider;

        return $old;
    }

    /**
     * Check whether the multiplexer must be considered or not.
     *
     * @return  bool
     */
    public function isMultiplexerConsidered()
    {
        return $this->_considerMultiplexer;
    }
}
