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

use Hoa\File;
use Hoa\Stream;

/**
 * Interface \Hoa\Console\Input.
 *
 * This interface represents the input of a program. Most of the time, this is
 * going to be `php://stdin` but it can be `/dev/tty` if the former has been
 * closed.
 *
 * @copyright  Copyright © 2007-2017 Hoa community
 * @license    New BSD License
 */
class Input implements Stream\IStream\In
{
    /**
     * Real input stream.
     *
     * @var \Hoa\Stream\IStream\In
     */
    protected $_input = null;



    /**
     * Wraps an `Hoa\Stream\IStream\In` stream.
     *
     * @param   \Hoa\Stream\IStream\In  $input    Input.
     */
    public function __construct(Stream\IStream\In $input = null)
    {
        if (null === $input) {
            if (defined('STDIN') &&
                false !== @stream_get_meta_data(STDIN)) {
                $input = new File\Read('php://stdin');
            } else {
                $input = new File\Read('/dev/tty');
            }
        }

        $this->_input = $input;

        return;
    }

    /**
     * Get underlying stream.
     *
     * @return \Hoa\Stream\IStream\In
     */
    public function getStream()
    {
        return $this->_input;
    }

    /**
     * Test for end-of-file.
     *
     * @return  bool
     */
    public function eof()
    {
        return $this->_input->eof();
    }

    /**
     * Read n characters.
     *
     * @param   int     $length    Length.
     * @return  string
     */
    public function read($length)
    {
        return $this->_input->read($length);
    }

    /**
     * Alias of $this->read().
     *
     * @param   int     $length    Length.
     * @return  string
     */
    public function readString($length)
    {
        return $this->_input->readString($length);
    }

    /**
     * Read a character.
     *
     * @return  string
     */
    public function readCharacter()
    {
        return $this->_input->readCharacter();
    }

    /**
     * Read a boolean.
     *
     * @return  bool
     */
    public function readBoolean()
    {
        return $this->_input->readBoolean();
    }

    /**
     * Read an integer.
     *
     * @param   int    $length    Length.
     * @return  int
     */
    public function readInteger($length = 1)
    {
        return $this->_input->readInteger($length);
    }

    /**
     * Read a float.
     *
     * @param   int     $length    Length.
     * @return  float
     */
    public function readFloat($length = 1)
    {
        return $this->_input->readFloat($length);
    }

    /**
     * Read an array.
     * Alias of the $this->scanf() method.
     *
     * @param   mixed   $argument    Argument (because the behavior is very
     *                               different according to the implementation).
     * @return  array
     */
    public function readArray($argument = null)
    {
        return $this->_input->readArray($argument);
    }

    /**
     * Read a line.
     *
     * @return  string
     */
    public function readLine()
    {
        return $this->_input->readLine();
    }

    /**
     * Read all, i.e. read as much as possible.
     *
     * @param   int  $offset    Offset.
     * @return  string
     */
    public function readAll($offset = 0)
    {
        return $this->_input->readAll($offset);
    }

    /**
     * Parse input from a stream according to a format.
     *
     * @param   string  $format    Format (see printf's formats).
     * @return  array
     */
    public function scanf($format)
    {
        return $this->_input->scanf($format);
    }
}
