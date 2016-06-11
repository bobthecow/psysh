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

    protected static $controlCharsRx = '/[\x00-\x1F\x7F]+/';
    protected static $controlCharsMap = array(
        "\0"   => '\0',
        "\t"   => '\t',
        "\n"   => '\n',
        "\v"   => '\v',
        "\f"   => '\f',
        "\r"   => '\r',
        "\033" => '\e',
    );

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

        $map = self::$controlCharsMap;
        $cchr = $this->styles['cchr'];
        $value = preg_replace_callback(self::$controlCharsRx, function ($c) use ($map, $cchr) {
            $s = '';
            $i = 0;
            $c = $c[0];
            do {
                $s .= isset($map[$c[$i]]) ? $map[$c[$i]] : sprintf('\x%02X', ord($c[$i]));
            } while (isset($c[++$i]));

            return "<{$cchr}>{$s}</{$cchr}>";
        }, $this->formatter->escape($value));

        $style = $this->styles[$style];

        return "<{$style}>{$value}</{$style}>";
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
