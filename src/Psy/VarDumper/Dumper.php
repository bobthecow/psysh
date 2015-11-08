<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\VarDumper;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\VarDumper\Cloner\Cursor;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * A PsySH-specialized CliDumper.
 */
class Dumper extends CliDumper
{
    private $formatter;

    public function __construct(OutputFormatter $formatter)
    {
        $this->formatter = $formatter;
        parent::__construct();
        $this->setColors(false);
    }

    /**
     * {@inheritdoc}
     */
    public function enterHash(Cursor $cursor, $type, $class, $hasChild)
    {
        if (Cursor::HASH_INDEXED === $type || Cursor::HASH_ASSOC === $type) {
            $class = 0;
        }
        parent::enterHash($cursor, $type, $class, $hasChild);
    }

    /**
     * {@inheritdoc}
     */
    protected function dumpKey(Cursor $cursor)
    {
        if (Cursor::HASH_INDEXED !== $cursor->hashType) {
            parent::dumpKey($cursor);
        }
    }

    protected function style($style, $value, $attr = array())
    {
        if ('ref' === $style) {
            $value = strtr($value, '@', '#');
        }
        $style = $this->styles[$style];
        $value = "<{$style}>" . $this->formatter->escape($value) . "</{$style}>";
        $cchr = $this->styles['cchr'];
        $value = preg_replace_callback(self::$controlCharsRx, function ($c) use ($cchr) {
            switch ($c[0]) {
                case "\t":
                    $c = '\t';
                    break;
                case "\n":
                    $c = '\n';
                    break;
                case "\v":
                    $c = '\v';
                    break;
                case "\f":
                    $c = '\f';
                    break;
                case "\r":
                    $c = '\r';
                    break;
                case "\033":
                    $c = '\e';
                    break;
                default:
                    $c = sprintf('\x%02X', ord($c[0]));
                    break;
            }

            return "<{$cchr}>{$c}</{$cchr}>";
        }, $value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function dumpLine($depth, $endOfValue = false)
    {
        if ($endOfValue && 0 < $depth) {
            $this->line .= ',';
        }
        $this->line = $this->formatter->format($this->line);
        parent::dumpLine($depth, $endOfValue);
    }
}
