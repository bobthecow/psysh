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

use Hoa\Event;

/**
 * Class \Hoa\Console\Window.
 *
 * Allow to manipulate the window.
 *
 * We can listen the event channel hoa://Event/Console/Window:resize to detect
 * if the window has been resized. Please, see the constructor documentation to
 * get more informations.
 *
 * @copyright  Copyright © 2007-2017 Hoa community
 * @license    New BSD License
 */
class Window implements Event\Source
{
    /**
     * Singleton (only for events).
     *
     * @var \Hoa\Console\Window
     */
    private static $_instance = null;



    /**
     * Set the event channel.
     * We need to declare(ticks = 1) in the main script to ensure that the event
     * is fired. Also, we need the pcntl_signal() function enabled.
     *
     */
    private function __construct()
    {
        Event::register(
            'hoa://Event/Console/Window:resize',
            $this
        );

        return;
    }

    /**
     * Singleton.
     *
     * @return  \Hoa\Console\Window
     */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

    /**
     * Set size to X lines and Y columns.
     *
     * @param   int  $x    X coordinate.
     * @param   int  $y    Y coordinate.
     * @return  void
     */
    public static function setSize($x, $y)
    {
        if (OS_WIN) {
            return;
        }

        Console::getOutput()->writeAll("\033[8;" . $y . ";" . $x . "t");

        return;
    }

    /**
     * Get current size (x and y) of the window.
     *
     * @return  array
     */
    public static function getSize()
    {
        if (OS_WIN) {
            $modecon = explode("\n", ltrim(Processus::execute('mode con')));

            $_y      = trim($modecon[2]);
            preg_match('#[^:]+:\s*([0-9]+)#', $_y, $matches);
            $y       = (int) $matches[1];

            $_x      = trim($modecon[3]);
            preg_match('#[^:]+:\s*([0-9]+)#', $_x, $matches);
            $x       = (int) $matches[1];

            return [
                'x' => $x,
                'y' => $y
            ];
        }

        $term = '';

        if (isset($_SERVER['TERM'])) {
            $term = 'TERM="' . $_SERVER['TERM'] . '" ';
        }

        $command = $term . 'tput cols && ' . $term . 'tput lines';
        $tput    = Processus::execute($command, false);

        if (!empty($tput)) {
            list($x, $y) = explode("\n", $tput);

            return [
                'x' => intval($x),
                'y' => intval($y)
            ];
        }

        // DECSLPP.
        Console::getOutput()->writeAll("\033[18t");

        $input = Console::getInput();

        // Read \033[8;y;xt.
        $input->read(4); // skip \033, [, 8 and ;.

        $x      = null;
        $y      = null;
        $handle = &$y;

        do {
            $char = $input->readCharacter();

            switch ($char) {
                case ';':
                    $handle = &$x;

                    break;

                case 't':
                    break 2;

                default:
                    if (false === ctype_digit($char)) {
                        break 2;
                    }

                    $handle .= $char;
            }
        } while (true);

        if (null === $x || null === $y) {
            return [
                'x' => 0,
                'y' => 0
            ];
        }

        return [
            'x' => (int) $x,
            'y' => (int) $y
        ];
    }

    /**
     * Move to X and Y (in pixels).
     *
     * @param   int  $x    X coordinate.
     * @param   int  $y    Y coordinate.
     * @return  void
     */
    public static function moveTo($x, $y)
    {
        if (OS_WIN) {
            return;
        }

        // DECSLPP.
        Console::getOutput()->writeAll("\033[3;" . $x . ";" . $y . "t");

        return;
    }

    /**
     * Get current position (x and y) of the window (in pixels).
     *
     * @return  array
     */
    public static function getPosition()
    {
        if (OS_WIN) {
            return;
        }

        // DECSLPP.
        Console::getOutput()->writeAll("\033[13t");

        $input = Console::getInput();

        // Read \033[3;x;yt.
        $input->read(4); // skip \033, [, 3 and ;.

        $x      = null;
        $y      = null;
        $handle = &$x;

        do {
            $char = $input->readCharacter();

            switch ($char) {
                case ';':
                    $handle = &$y;

                    break;

                case 't':
                    break 2;

                default:
                    $handle .= $char;
            }
        } while (true);

        return [
            'x' => (int) $x,
            'y' => (int) $y
        ];
    }

    /**
     * Scroll whole page.
     * Directions can be:
     *     • u, up,    ↑ : scroll whole page up;
     *     • d, down,  ↓ : scroll whole page down.
     * Directions can be concatenated by a single space.
     *
     * @param   string  $directions    Directions.
     * @param   int     $repeat        How many times do we scroll?
     * @return  void
     */
    public static function scroll($directions, $repeat = 1)
    {
        if (OS_WIN) {
            return;
        }

        if (1 > $repeat) {
            return;
        } elseif (1 === $repeat) {
            $handle = explode(' ', $directions);
        } else {
            $handle = explode(' ', $directions, 1);
        }

        $tput  = Console::getTput();
        $count = ['up' => 0, 'down' => 0];

        foreach ($handle as $direction) {
            switch ($direction) {
                case 'u':
                case 'up':
                case '↑':
                    ++$count['up'];

                    break;

                case 'd':
                case 'down':
                case '↓':
                    ++$count['down'];

                    break;
            }
        }

        $output = Console::getOutput();

        if (0 < $count['up']) {
            $output->writeAll(
                str_replace(
                    '%p1%d',
                    $count['up'] * $repeat,
                    $tput->get('parm_index')
                )
            );
        }

        if (0 < $count['down']) {
            $output->writeAll(
                str_replace(
                    '%p1%d',
                    $count['down'] * $repeat,
                    $tput->get('parm_rindex')
                )
            );
        }

        return;
    }

    /**
     * Minimize the window.
     *
     * @return  void
     */
    public static function minimize()
    {
        if (OS_WIN) {
            return;
        }

        // DECSLPP.
        Console::getOutput()->writeAll("\033[2t");

        return;
    }

    /**
     * Restore the window (de-minimize).
     *
     * @return  void
     */
    public static function restore()
    {
        if (OS_WIN) {
            return;
        }

        Console::getOutput()->writeAll("\033[1t");

        return;
    }

    /**
     * Raise the window to the front of the stacking order.
     *
     * @return  void
     */
    public static function raise()
    {
        if (OS_WIN) {
            return;
        }

        Console::getOutput()->writeAll("\033[5t");

        return;
    }

    /**
     * Lower the window to the bottom of the stacking order.
     *
     * @return  void
     */
    public static function lower()
    {
        if (OS_WIN) {
            return;
        }

        Console::getOutput()->writeAll("\033[6t");

        return;
    }

    /**
     * Set title.
     *
     * @param   string  $title    Title.
     * @return  void
     */
    public static function setTitle($title)
    {
        if (OS_WIN) {
            return;
        }

        // DECSLPP.
        Console::getOutput()->writeAll("\033]0;" . $title . "\033\\");

        return;
    }

    /**
     * Get title.
     *
     * @return  string
     */
    public static function getTitle()
    {
        if (OS_WIN) {
            return;
        }

        // DECSLPP.
        Console::getOutput()->writeAll("\033[21t");

        $input  = Console::getInput();
        $read   = [$input->getStream()->getStream()];
        $write  = [];
        $except = [];
        $out    = null;

        if (0 === stream_select($read, $write, $except, 0, 50000)) {
            return $out;
        }

        // Read \033]l<title>\033\
        $input->read(3); // skip \033, ] and l.

        do {
            $char = $input->readCharacter();

            if ("\033" === $char) {
                $chaar = $input->readCharacter();

                if ('\\' === $chaar) {
                    break;
                }

                $char .= $chaar;
            }

            $out .= $char;
        } while (true);

        return $out;
    }

    /**
     * Get label.
     *
     * @return  string
     */
    public static function getLabel()
    {
        if (OS_WIN) {
            return;
        }

        // DECSLPP.
        Console::getOutput()->writeAll("\033[20t");

        $input  = Console::getInput();
        $read   = [$input->getStream()->getStream()];
        $write  = [];
        $except = [];
        $out    = null;

        if (0 === stream_select($read, $write, $except, 0, 50000)) {
            return $out;
        }

        // Read \033]L<label>\033\
        $input->read(3); // skip \033, ] and L.

        do {
            $char = $input->readCharacter();

            if ("\033" === $char) {
                $chaar = $input->readCharacter();

                if ('\\' === $chaar) {
                    break;
                }

                $char .= $chaar;
            }

            $out .= $char;
        } while (true);

        return $out;
    }

    /**
     * Refresh the window.
     *
     * @return  void
     */
    public static function refresh()
    {
        if (OS_WIN) {
            return;
        }

        // DECSLPP.
        Console::getOutput()->writeAll("\033[7t");

        return;
    }

    /**
     * Set clipboard value.
     *
     * @param   string  $data    Data to copy.
     * @return  void
     */
    public static function copy($data)
    {
        if (OS_WIN) {
            return;
        }

        $out                 = "\033]52;;" . base64_encode($data) . "\033\\";
        $output              = Console::getOutput();
        $considerMultiplexer = $output->considerMultiplexer(true);

        $output->writeAll($out);
        $output->considerMultiplexer($considerMultiplexer);

        return;
    }
}

/**
 * Advanced interaction.
 */
Console::advancedInteraction();

/**
 * Event.
 */
if (function_exists('pcntl_signal')) {
    Window::getInstance();
    pcntl_signal(
        SIGWINCH,
        function () {
            static $_window = null;

            if (null === $_window) {
                $_window = Window::getInstance();
            }

            Event::notify(
                'hoa://Event/Console/Window:resize',
                $_window,
                new Event\Bucket([
                    'size' => Window::getSize()
                ])
            );
        }
    );
}
