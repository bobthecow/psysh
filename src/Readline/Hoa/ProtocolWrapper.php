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

namespace Hoa\Protocol
{

/**
 * Class \Hoa\Protocol\Wrapper.
 *
 * Stream wrapper for the `hoa://` protocol.
 *
 * @copyright  Copyright © 2007-2017 Hoa community
 * @license    New BSD License
 */
class Wrapper
{
    /**
     * Opened stream.
     *
     * @var resource
     */
    private $_stream     = null;

    /**
     * Stream name (filename).
     *
     * @var string
     */
    private $_streamName = null;

    /**
     * Stream context (given by the streamWrapper class).
     *
     * @var resource
     */
    public $context      = null;



    /**
     * Get the real path of the given URL.
     * Could return false if the path cannot be reached.
     *
     * @param   string  $path      Path (or URL).
     * @param   bool    $exists    If true, try to find the first that exists,
     * @return  mixed
     */
    public static function realPath($path, $exists = true)
    {
        return Node::getRoot()->resolve($path, $exists);
    }

    /**
     * Retrieve the underlying resource.
     *
     * @param   int     $castAs    Can be STREAM_CAST_FOR_SELECT when
     *                             stream_select() is calling stream_cast() or
     *                             STREAM_CAST_AS_STREAM when stream_cast() is
     *                             called for other uses.
     * @return  resource
     */
    public function stream_cast($castAs)
    {
        return false;
    }

    /**
     * Close a resource.
     * This method is called in response to fclose().
     * All resources that were locked, or allocated, by the wrapper should be
     * released.
     *
     * @return  void
     */
    public function stream_close()
    {
        if (true === @fclose($this->getStream())) {
            $this->_stream     = null;
            $this->_streamName = null;
        }

        return;
    }

    /**
     * Tests for end-of-file on a file pointer.
     * This method is called in response to feof().
     *
     * access   public
     * @return  bool
     */
    public function stream_eof()
    {
        return feof($this->getStream());
    }

    /**
     * Flush the output.
     * This method is called in respond to fflush().
     * If we have cached data in our stream but not yet stored it into the
     * underlying storage, we should do so now.
     *
     * @return  bool
     */
    public function stream_flush()
    {
        return fflush($this->getStream());
    }

    /**
     * Advisory file locking.
     * This method is called in response to flock(), when file_put_contents()
     * (when flags contains LOCK_EX), stream_set_blocking() and when closing the
     * stream (LOCK_UN).
     *
     * @param   int     $operation    Operation is one the following:
     *                                  * LOCK_SH to acquire a shared lock (reader) ;
     *                                  * LOCK_EX to acquire an exclusive lock (writer) ;
     *                                  * LOCK_UN to release a lock (shared or exclusive) ;
     *                                  * LOCK_NB if we don't want flock() to
     *                                    block while locking (not supported on
     *                                    Windows).
     * @return  bool
     */
    public function stream_lock($operation)
    {
        return flock($this->getStream(), $operation);
    }

    /**
     * Change stream options.
     * This method is called to set metadata on the stream. It is called when
     * one of the following functions is called on a stream URL: touch, chmod,
     * chown or chgrp.
     *
     * @param   string    $path      The file path or URL to set metadata.
     * @param   int       $option    One of the following constant:
     *                                 * STREAM_META_TOUCH,
     *                                 * STREAM_META_OWNER_NAME,
     *                                 * STREAM_META_OWNER,
     *                                 * STREAM_META_GROUP_NAME,
     *                                 * STREAM_META_GROUP,
     *                                 * STREAM_META_ACCESS.
     * @param   mixed     $values    Arguments of touch, chmod, chown and chgrp.
     * @return  bool
     */
    public function stream_metadata($path, $option, $values)
    {
        $path = static::realPath($path, false);

        switch ($option) {
            case STREAM_META_TOUCH:
                $arity = count($values);

                if (0 === $arity) {
                    $out = touch($path);
                } elseif (1 === $arity) {
                    $out = touch($path, $values[0]);
                } else {
                    $out = touch($path, $values[0], $values[1]);
                }

                break;

            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                $out = chown($path, $values);

                break;

            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                $out = chgrp($path, $values);

                break;

            case STREAM_META_ACCESS:
                $out = chmod($path, $values);

                break;

            default:
                $out = false;
        }

        return $out;
    }

    /**
     * Open file or URL.
     * This method is called immediately after the wrapper is initialized (f.e.
     * by fopen() and file_get_contents()).
     *
     * @param   string  $path           Specifies the URL that was passed to the
     *                                  original function.
     * @param   string  $mode           The mode used to open the file, as
     *                                  detailed for fopen().
     * @param   int     $options        Holds additional flags set by the
     *                                  streams API. It can hold one or more of
     *                                  the following values OR'd together:
     *                                    * STREAM_USE_PATH, if path is relative,
     *                                      search for the resource using the
     *                                      include_path;
     *                                    * STREAM_REPORT_ERRORS, if this is
     *                                    set, you are responsible for raising
     *                                    errors using trigger_error during
     *                                    opening the stream. If this is not
     *                                    set, you should not raise any errors.
     * @param   string  &$openedPath    If the $path is opened successfully, and
     *                                  STREAM_USE_PATH is set in $options,
     *                                  $openedPath should be set to the full
     *                                  path of the file/resource that was
     *                                  actually opened.
     * @return  bool
     */
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        $path = static::realPath($path, 'r' === $mode[0]);

        if (Protocol::NO_RESOLUTION === $path) {
            return false;
        }

        if (null === $this->context) {
            $openedPath = fopen($path, $mode, $options & STREAM_USE_PATH);
        } else {
            $openedPath = fopen(
                $path,
                $mode,
                $options & STREAM_USE_PATH,
                $this->context
            );
        }

        if (false === is_resource($openedPath)) {
            return false;
        }

        $this->_stream     = $openedPath;
        $this->_streamName = $path;

        return true;
    }

    /**
     * Read from stream.
     * This method is called in response to fread() and fgets().
     *
     * @param   int     $count    How many bytes of data from the current
     *                            position should be returned.
     * @return  string
     */
    public function stream_read($count)
    {
        return fread($this->getStream(), $count);
    }

    /**
     * Seek to specific location in a stream.
     * This method is called in response to fseek().
     * The read/write position of the stream should be updated according to the
     * $offset and $whence.
     *
     * @param   int     $offset    The stream offset to seek to.
     * @param   int     $whence    Possible values:
     *                               * SEEK_SET to set position equal to $offset
     *                                 bytes ;
     *                               * SEEK_CUR to set position to current
     *                                 location plus $offsete ;
     *                               * SEEK_END to set position to end-of-file
     *                                 plus $offset.
     * @return  bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return 0 === fseek($this->getStream(), $offset, $whence);
    }

    /**
     * Retrieve information about a file resource.
     * This method is called in response to fstat().
     *
     * @return  array
     */
    public function stream_stat()
    {
        return fstat($this->getStream());
    }

    /**
     * Retrieve the current position of a stream.
     * This method is called in response to ftell().
     *
     * @return  int
     */
    public function stream_tell()
    {
        return ftell($this->getStream());
    }

    /**
     * Truncate a stream to a given length.
     *
     * @param   int     $size    Size.
     * @return  bool
     */
    public function stream_truncate($size)
    {
        return ftruncate($this->getStream(), $size);
    }

    /**
     * Write to stream.
     * This method is called in response to fwrite().
     *
     * @param   string  $data    Should be stored into the underlying stream.
     * @return  int
     */
    public function stream_write($data)
    {
        return fwrite($this->getStream(), $data);
    }

    /**
     * Close directory handle.
     * This method is called in to closedir().
     * Any resources which were locked, or allocated, during opening and use of
     * the directory stream should be released.
     *
     * @return  void
     */
    public function dir_closedir()
    {
        closedir($this->getStream());
        $this->_stream     = null;
        $this->_streamName = null;

        return;
    }

    /**
     * Open directory handle.
     * This method is called in response to opendir().
     *
     * @param   string  $path       Specifies the URL that was passed to opendir().
     * @param   int     $options    Whether or not to enforce safe_mode (0x04).
     *                              It is not used here.
     * @return  bool
     */
    public function dir_opendir($path, $options)
    {
        $path   = static::realPath($path);
        $handle = null;

        if (null === $this->context) {
            $handle = @opendir($path);
        } else {
            $handle = @opendir($path, $this->context);
        }

        if (false === $handle) {
            return false;
        }

        $this->_stream     = $handle;
        $this->_streamName = $path;

        return true;
    }

    /**
     * Read entry from directory handle.
     * This method is called in response to readdir().
     *
     * @return  mixed
     */
    public function dir_readdir()
    {
        return readdir($this->getStream());
    }

    /**
     * Rewind directory handle.
     * This method is called in response to rewinddir().
     * Should reset the output generated by self::dir_readdir, i.e. the next
     * call to self::dir_readdir should return the first entry in the location
     * returned by self::dir_opendir.
     *
     * @return  void
     */
    public function dir_rewinddir()
    {
        return rewinddir($this->getStream());
    }

    /**
     * Create a directory.
     * This method is called in response to mkdir().
     *
     * @param   string  $path       Directory which should be created.
     * @param   int     $mode       The value passed to mkdir().
     * @param   int     $options    A bitwise mask of values.
     * @return  bool
     */
    public function mkdir($path, $mode, $options)
    {
        if (null === $this->context) {
            return mkdir(
                static::realPath($path, false),
                $mode,
                $options | STREAM_MKDIR_RECURSIVE
            );
        }

        return mkdir(
            static::realPath($path, false),
            $mode,
            $options | STREAM_MKDIR_RECURSIVE,
            $this->context
        );
    }

    /**
     * Rename a file or directory.
     * This method is called in response to rename().
     * Should attempt to rename $from to $to.
     *
     * @param   string  $from    The URL to current file.
     * @param   string  $to      The URL which $from should be renamed to.
     * @return  bool
     */
    public function rename($from, $to)
    {
        if (null === $this->context) {
            return rename(static::realPath($from), static::realPath($to, false));
        }

        return rename(
            static::realPath($from),
            static::realPath($to, false),
            $this->context
        );
    }

    /**
     * Remove a directory.
     * This method is called in response to rmdir().
     *
     * @param   string  $path       The directory URL which should be removed.
     * @param   int     $options    A bitwise mask of values. It is not used
     *                              here.
     * @return  bool
     */
    public function rmdir($path, $options)
    {
        if (null === $this->context) {
            return rmdir(static::realPath($path));
        }

        return rmdir(static::realPath($path), $this->context);
    }

    /**
     * Delete a file.
     * This method is called in response to unlink().
     *
     * @param   string  $path    The file URL which should be deleted.
     * @return  bool
     */
    public function unlink($path)
    {
        if (null === $this->context) {
            return unlink(static::realPath($path));
        }

        return unlink(static::realPath($path), $this->context);
    }

    /**
     * Retrieve information about a file.
     * This method is called in response to all stat() related functions.
     *
     * @param   string  $path     The file URL which should be retrieve
     *                            information about.
     * @param   int     $flags    Holds additional flags set by the streams API.
     *                            It can hold one or more of the following
     *                            values OR'd together.
     *                            STREAM_URL_STAT_LINK: for resource with the
     *                            ability to link to other resource (such as an
     *                            HTTP location: forward, or a filesystem
     *                            symlink). This flag specified that only
     *                            information about the link itself should be
     *                            returned, not the resource pointed to by the
     *                            link. This flag is set in response to calls to
     *                            lstat(), is_link(), or filetype().
     *                            STREAM_URL_STAT_QUIET: if this flag is set,
     *                            our wrapper should not raise any errors. If
     *                            this flag is not set, we are responsible for
     *                            reporting errors using the trigger_error()
     *                            function during stating of the path.
     * @return  array
     */
    public function url_stat($path, $flags)
    {
        $path = static::realPath($path);

        if (Protocol::NO_RESOLUTION === $path) {
            if ($flags & STREAM_URL_STAT_QUIET) {
                return 0;
            } else {
                return trigger_error(
                    'Path ' . $path . ' cannot be resolved.',
                    E_WARNING
                );
            }
        }

        if ($flags & STREAM_URL_STAT_LINK) {
            return @lstat($path);
        }

        return @stat($path);
    }

    /**
     * Get stream resource.
     *
     * @return  resource
     */
    public function getStream()
    {
        return $this->_stream;
    }

    /**
     * Get stream name.
     *
     * @return  resource
     */
    public function getStreamName()
    {
        return $this->_streamName;
    }
}

/**
 * Register the `hoa://` protocol.
 */
stream_wrapper_register('hoa', Wrapper::class);

}

namespace
{

/**
 * Alias of `Hoa\Protocol::resolve` method.
 *
 * @param   string  $path      Path to resolve.
 * @param   bool    $exists    If `true`, try to find the first that exists,
 *                             else return the first solution.
 * @param   bool    $unfold    Return all solutions instead of one.
 * @return  mixed
 */
if (!function_exists('resolve')) {
    function resolve($path, $exists = true, $unfold = false)
    {
        return Hoa\Protocol::getInstance()->resolve($path, $exists, $unfold);
    }
}

}
