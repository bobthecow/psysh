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

/**
 * @group isolation-fail
 */
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
}
