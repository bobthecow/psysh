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
        $dump = $this->dump("foo\nbar");

        $this->assertSame(<<<'TEXT'
<<<EOS
foo
bar
EOS
TEXT, $dump);
        $this->assertSame("foo\nbar", $this->evaluateDump($dump));
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
        $dump = $this->dump('abcd'."\n".'efgh', 3);

        $this->assertSame(<<<'TEXT'
<<<EOS
abc…2
efg
EOS…1
TEXT, $dump);
    }

    public function testNestedMultilineStringsUseIndentedHeredoc(): void
    {
        $dump = $this->dump(["foo\nbar"]);

        $this->assertSame(<<<'TEXT'
[
  <<<EOS
    foo
    bar
    EOS,
]
TEXT, $dump);
        $this->assertSame(["foo\nbar"], $this->evaluateDump($dump));
    }

    public function testMultilineStringsDoNotEscapeQuotesInsideHeredoc(): void
    {
        $dump = $this->dump("\"quoted\"\nbar");

        $this->assertSame(<<<'TEXT'
<<<EOS
"quoted"
bar
EOS
TEXT, $dump);
        $this->assertSame("\"quoted\"\nbar", $this->evaluateDump($dump));
    }

    public function testMultilineStringsAvoidEosWhenContentWouldCloseTheHeredoc(): void
    {
        $dump = $this->dump("  EOS\nbar");

        $this->assertSame(<<<'TEXT'
<<<EOS_2
  EOS
bar
EOS_2
TEXT, $dump);
        $this->assertSame("  EOS\nbar", $this->evaluateDump($dump));
    }

    public function testMultilineStringsCanUseTripleQuoteStyle(): void
    {
        $this->assertSame(<<<'TEXT'
"""
foo\n
bar
"""
TEXT, $this->dump("foo\nbar", 0, true));
    }

    public function testDecoratedSingleLineStringsHighlightQuotes(): void
    {
        $formatter = $this->createFormatter(true);
        $dump = $this->dumpWithFormatter($formatter, 'foo');

        $this->assertSame(
            $formatter->getStyle('string')->apply('"')
            .$formatter->getStyle('string')->apply('foo')
            .$formatter->getStyle('string')->apply('"'),
            $dump
        );
    }

    public function testDecoratedHeredocMarkersKeepArrowsNeutralAndLabelsStyled(): void
    {
        $formatter = $this->createFormatter(true);
        $dump = $this->dumpWithFormatter($formatter, "foo\nbar");
        $lines = \explode(\PHP_EOL, $dump);

        $this->assertSame('<<<'.$formatter->getStyle('string')->apply('EOS'), $lines[0]);
        $this->assertSame($formatter->getStyle('string')->apply('EOS'), $lines[3]);
    }

    public function testDecoratedTripleQuoteMultilineStringsHighlightQuotes(): void
    {
        $formatter = $this->createFormatter(true);
        $dump = $this->dumpWithFormatter($formatter, "foo\nbar", 0, true);
        $lines = \explode(\PHP_EOL, $dump);

        $this->assertSame($formatter->getStyle('string')->apply('"""'), $lines[0]);
        $this->assertSame($formatter->getStyle('string')->apply('"""'), $lines[3]);
    }

    public function testDecoratedAssociativeStringKeysHighlightQuotesWithKeyStyle(): void
    {
        $formatter = $this->createFormatter(true);
        $dump = $this->dumpWithFormatter($formatter, ['key' => 1]);
        $lines = \explode(\PHP_EOL, $dump);

        $this->assertSame(
            '  '
            .$formatter->getStyle('array_key')->apply('"')
            .$formatter->getStyle('array_key')->apply('key')
            .$formatter->getStyle('array_key')->apply('"')
            .' => '
            .$formatter->getStyle('number')->apply('1')
            .',',
            $lines[1]
        );
    }

    public function testAssociativeArrayReferencesKeepHardRefMarkers(): void
    {
        $value = 123;
        $dump = $this->dump([
            'a' => &$value,
            'b' => &$value,
        ]);

        $this->assertSame(<<<'TEXT'
[
  "a" => &1 123,
  "b" => &1 123,
]
TEXT, $dump);
    }

    public function testStyleOmitsIfLinksMarkersWhenLinksAreUnavailable(): void
    {
        $dumper = new class(new OutputFormatter(false)) extends Dumper {
            public function inspectStyle(string $style, string $value, array $attr = []): string
            {
                return $this->style($style, $value, $attr);
            }
        };
        $this->configureDumperStyles($dumper);

        $this->assertSame('', $dumper->inspectStyle('default', '^', ['if_links' => true]));
    }

    public function testObjectPropertiesUseTrailingCommasLikeVarExport(): void
    {
        $dump = $this->dump(new class() {
            public $foo = 1;
        });

        $this->assertStringContainsString("+foo: 1,\n}", $dump);
    }

    private function dump($value, int $maxStringWidth = 0, bool $useDeprecatedMultilineStrings = false): string
    {
        return $this->dumpWithFormatter($this->createFormatter(false), $value, $maxStringWidth, $useDeprecatedMultilineStrings);
    }

    private function dumpWithFormatter(OutputFormatter $formatter, $value, int $maxStringWidth = 0, bool $useDeprecatedMultilineStrings = false): string
    {
        $dumper = new Dumper($formatter, false, $useDeprecatedMultilineStrings);
        $this->configureDumperStyles($dumper);
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

    private function configureDumperStyles(Dumper $dumper): void
    {
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
            'key'       => 'array_key',
            'index'     => 'number',
        ]);
    }

    private function createFormatter(bool $decorated): OutputFormatter
    {
        $formatter = new OutputFormatter($decorated);

        $formatter->setStyle('number', new OutputFormatterStyle('magenta'));
        $formatter->setStyle('integer', new OutputFormatterStyle('magenta'));
        $formatter->setStyle('float', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('const', new OutputFormatterStyle('cyan'));
        $formatter->setStyle('string', new OutputFormatterStyle('green'));
        $formatter->setStyle('array_key', new OutputFormatterStyle('blue'));
        $formatter->setStyle('default', new OutputFormatterStyle());
        $formatter->setStyle('class', new OutputFormatterStyle('blue'));
        $formatter->setStyle('public', new OutputFormatterStyle());
        $formatter->setStyle('protected', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('private', new OutputFormatterStyle('red'));
        $formatter->setStyle('comment', new OutputFormatterStyle('blue'));

        return $formatter;
    }

    /**
     * @return mixed
     */
    private function evaluateDump(string $dump)
    {
        return eval('return '.$dump.';');
    }
}
