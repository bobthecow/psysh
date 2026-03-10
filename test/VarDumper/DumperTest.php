<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\VarDumper;

use Psy\Test\TestCase;
use Psy\VarDumper\Dumper;
use Psy\VarDumper\Presenter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\VarDumper\Cloner\VarCloner;

class DumperTest extends TestCase
{
    public function testStringDumpKeepsLiteralBackslashesVisible(): void
    {
        $this->assertSame('"\\n"', $this->dump("\n"));
        $this->assertSame('"foo\\n"', $this->dump("foo\n"));
        $this->assertSame('"\\\\n"', $this->dump('\\n'));
        $this->assertSame('"\\""', $this->dump('"'));
    }

    public function testStringDumpPreservesBackslashesBeforeFormatterMarkup(): void
    {
        $this->assertSame('"\\\\<<"', $this->dump('\\<<'));
        $this->assertSame('"\\\\\\\\<<"', $this->dump('\\\\<<'));
    }

    public function testStringDumpEscapesLiteralDollarSigns(): void
    {
        $formatter = new OutputFormatter(false);
        $presenter = new Presenter($formatter);

        foreach ([
            'price $5' => '"price \\$5"',
            '$foo'     => '"\\$foo"',
            '$$'       => '"\\$\\$"',
        ] as $input => $expected) {
            $this->assertSame($expected, $this->dump($input));
            $this->assertSame($expected, $formatter->format($presenter->present($input)));
            $this->assertSame($expected, $presenter->present($input, null, Presenter::RAW));
        }
    }

    public function testAssociativeStringKeysUseSameEscapingRules(): void
    {
        $expected = <<<'TEXT'
[
  "\\<<" => 1,
]
TEXT;

        $this->assertSame($expected, $this->dump(['\\<<' => 1]));
    }

    public function testStringDumpBreaksLinesAfterNewlineEscapes(): void
    {
        $expected = <<<'TEXT'
"""
foo\n
bar
"""
TEXT;

        $this->assertSame($expected, $this->dump("foo\nbar"));
    }

    public function testPresenterOutputSurvivesFinalFormattingPass(): void
    {
        $formatter = new OutputFormatter(false);
        $presenter = new Presenter($formatter);

        $this->assertSame('"\\\\<<"', $formatter->format($presenter->present('\\<<')));
        $this->assertSame('"\\\\n"', $formatter->format($presenter->present('\\n')));
    }

    public function testPresenterRawOutputSkipsFormatterEscaping(): void
    {
        $presenter = new Presenter(new OutputFormatter(false));

        $this->assertSame('"\\\\<<"', $presenter->present('\\<<', null, Presenter::RAW));
        $this->assertSame('"\\n"', $presenter->present("\n", null, Presenter::RAW));
        $this->assertSame('"foo\\n"', $presenter->present("foo\n", null, Presenter::RAW));
        $this->assertSame('"\\\\n"', $presenter->present('\\n', null, Presenter::RAW));
        $this->assertSame('"\\""', $presenter->present('"', null, Presenter::RAW));
    }

    public function testBinaryStringDumpPreservesSingleTrailingNewlineLayout(): void
    {
        $this->assertSame('b"ÿ\\n"', $this->dump("\xFF\n"));
        $this->assertSame('b"Aÿ\\n"', $this->dump("A\xFF\n"));
    }

    public function testMultilineStringDumpTruncatesEachLineLikeSymfony(): void
    {
        $expected = <<<'TEXT'
"""
abc…2
efg
"""…1
TEXT;

        $this->assertSame($expected, $this->dump('abcd'."\n".'efgh', 3));
    }

    private function dump($value, int $maxStringWidth = 0): string
    {
        $formatter = new OutputFormatter(false);

        foreach ([
            'number',
            'integer',
            'float',
            'const',
            'string',
            'default',
            'class',
            'public',
            'protected',
            'private',
            'comment',
        ] as $style) {
            $formatter->setStyle($style, new OutputFormatterStyle());
        }

        $dumper = new Dumper($formatter);
        $dumper->setStyles([
            'num'       => 'number',
            'integer'   => 'integer',
            'float'     => 'float',
            'const'     => 'const',
            'str'       => 'string',
            'cchr'      => 'default',
            'note'      => 'class',
            'ref'       => 'default',
            'public'    => 'public',
            'protected' => 'protected',
            'private'   => 'private',
            'meta'      => 'comment',
            'key'       => 'comment',
            'index'     => 'number',
        ]);
        $dumper->setMaxStringWidth($maxStringWidth);

        $output = '';
        $dumper->dump((new VarCloner())->cloneVar($value), function ($line, $depth) use (&$output): void {
            if ($depth < 0) {
                return;
            }

            if ($output !== '') {
                $output .= \PHP_EOL;
            }

            $output .= \str_repeat('  ', $depth).$line;
        });

        return $output;
    }
}
