<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Layout;

use Psy\Readline\Interactive\Layout\DisplayString;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;

class DisplayStringTest extends TestCase
{
    public function testWidthCountsFormatterLikeTagsAsLiteralText()
    {
        $this->assertSame(14, DisplayString::width('<info>x</info>'));
    }

    public function testWidthWithoutFormattingParsesFormatterTags()
    {
        $formatter = new OutputFormatter();

        $this->assertSame(1, DisplayString::widthWithoutFormatting('<info>x</info>', $formatter));
    }

    public function testWidthWithoutAnsiStripsAnsiButNotFormatterTags()
    {
        $text = "\033[31mred\033[0m <info>x</info>";

        $this->assertSame(18, DisplayString::widthWithoutAnsi($text));
    }

    public function testWidthWithoutAnsiStripsOscHyperlinks()
    {
        $text = "\033]8;;https://psysh.org\033\\link\033]8;;\033\\";

        $this->assertSame(4, DisplayString::widthWithoutAnsi($text));
    }

    public function testExtractsOscHyperlinkDisplayRange()
    {
        $text = "See \033[1m\033]8;;https://php.net/array-values\033\\array_values()\033]8;;\033\\\033[22m next";

        $this->assertSame([
            [
                'uri'   => 'https://php.net/array-values',
                'label' => 'array_values()',
                'start' => 4,
                'end'   => 18,
            ],
        ], DisplayString::hyperlinks($text));
    }

    public function testFindsOscHyperlinkAtDisplayCell()
    {
        $text = "界 \033]8;id=example;https://php.net/time\007time()\033]8;;\007";

        $this->assertNull(DisplayString::hyperlinkAt($text, 2));
        $link = DisplayString::hyperlinkAt($text, 3);
        $this->assertNotNull($link);
        $this->assertSame('time()', $link['label']);
        $this->assertSame($link, DisplayString::hyperlinkAt($text, 8));
        $this->assertNull(DisplayString::hyperlinkAt($text, 9));
    }

    public function testFindsMultipleOscHyperlinksOnOneLine()
    {
        $time = "\033]8;;https://php.net/time\033\\time()\033]8;;\033\\";
        $date = "\033]8;;https://php.net/date\033\\date()\033]8;;\033\\";
        $text = $time.', '.$date;

        $links = DisplayString::hyperlinks($text);
        $this->assertCount(2, $links);
        $this->assertSame('time()', $links[0]['label']);
        $this->assertSame(0, $links[0]['start']);
        $this->assertSame('date()', $links[1]['label']);
        $this->assertSame(8, $links[1]['start']);
        $this->assertSame($links[1], DisplayString::hyperlinkAt($text, 9));
    }

    public function testUnderlinesOneOscHyperlinkWithoutChangingItsTarget()
    {
        $time = "\033]8;;https://php.net/time\033\\time()\033]8;;\033\\";
        $date = "\033]8;;https://php.net/date\033\\date()\033]8;;\033\\";

        $result = DisplayString::underlineHyperlink($time.', '.$date, 8);

        $this->assertStringNotContainsString("\033[4mtime()\033[24m", $result);
        $this->assertStringContainsString("\033[4mdate()\033[24m", $result);
        $this->assertSame(['https://php.net/time', 'https://php.net/date'], \array_column(DisplayString::hyperlinks($result), 'uri'));
    }

    public function testUsesDoubleUnderlineForAlreadyUnderlinedLink()
    {
        $link = "\033]8;;https://php.net/datetimeimmutable\033\\\033[34;4mDateTimeImmutable\033[39;24m\033]8;;\033\\";

        $result = DisplayString::underlineHyperlink($link, 0);

        $this->assertStringContainsString("\033[34;21mDateTimeImmutable\033[39;24m\033[24m", $result);
    }

    public function testUsesDoubleUnderlineWhenLinkInheritsUnderlineStyle()
    {
        $link = "\033[34;4m\033]8;;https://php.net/datetimeimmutable\033\\DateTimeImmutable\033]8;;\033\\\033[39;24m";

        $result = DisplayString::underlineHyperlink($link, 0);

        $this->assertStringContainsString("\033]8;;https://php.net/datetimeimmutable\033\\\033[21mDateTimeImmutable\033[24m", $result);
    }

    public function testDoesNotTreatExtendedColorValuesAsUnderlineStyles()
    {
        $link = "\033[38;5;4m\033]8;;https://php.net/time\033\\\033[38;5;4mtime()\033[39m\033]8;;\033\\";

        $result = DisplayString::underlineHyperlink($link, 0);

        $this->assertStringContainsString("\033[38;5;4m\033]8;;https://php.net/time\033\\\033[4m\033[38;5;4mtime()", $result);
        $this->assertStringNotContainsString("\033[38;5;21m", $result);
    }
}
