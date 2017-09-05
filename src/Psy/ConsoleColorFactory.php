<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use JakubOnderka\PhpConsoleColor\ConsoleColor;
use JakubOnderka\PhpConsoleHighlighter\Highlighter;

/**
 * Builds `ConsoleColor` instances configured according to the given color mode.
 */
class ConsoleColorFactory
{
    private $colorMode;

    /**
     * @param string $colorMode
     */
    public function __construct($colorMode)
    {
        $this->colorMode = $colorMode;
    }

    /**
     * Get a `ConsoleColor` instance configured according to the given color
     * mode.
     *
     * @return ConsoleColor
     */
    public function getConsoleColor()
    {
        if ($this->colorMode === Configuration::COLOR_MODE_AUTO) {
            return $this->getDefaultConsoleColor();
        } elseif ($this->colorMode === Configuration::COLOR_MODE_FORCED) {
            return $this->getForcedConsoleColor();
        } elseif ($this->colorMode === Configuration::COLOR_MODE_DISABLED) {
            return $this->getDisabledConsoleColor();
        }
    }

    private function getDefaultConsoleColor()
    {
        $color = new ConsoleColor();
        $color->addTheme(Highlighter::LINE_NUMBER, array('blue'));
        $color->addTheme(Highlighter::TOKEN_KEYWORD, array('yellow'));
        $color->addTheme(Highlighter::TOKEN_STRING, array('green'));
        $color->addTheme(Highlighter::TOKEN_COMMENT, array('dark_gray'));

        return $color;
    }

    private function getForcedConsoleColor()
    {
        $color = $this->getDefaultConsoleColor();
        $color->setForceStyle(true);

        return $color;
    }

    private function getDisabledConsoleColor()
    {
        $color = new ConsoleColor();

        $color->addTheme(Highlighter::TOKEN_STRING, array('none'));
        $color->addTheme(Highlighter::TOKEN_COMMENT, array('none'));
        $color->addTheme(Highlighter::TOKEN_KEYWORD, array('none'));
        $color->addTheme(Highlighter::TOKEN_DEFAULT, array('none'));
        $color->addTheme(Highlighter::TOKEN_HTML, array('none'));
        $color->addTheme(Highlighter::ACTUAL_LINE_MARK, array('none'));
        $color->addTheme(Highlighter::LINE_NUMBER, array('none'));

        return $color;
    }
}
