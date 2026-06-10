<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\ManualWrapper;
use Psy\Output\Theme;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;

class ManualWrapperTest extends TestCase
{
    public function testLongestWordWidthCountsEscapedVisibleTags()
    {
        $formatter = new OutputFormatter();
        (new Theme('modern'))->applyStyles($formatter, !Theme::grayExists($formatter));

        $wrapper = new ManualWrapper($formatter);

        $this->assertSame(19, $wrapper->longestWordWidth('\\<notastyle\\>resource <info>ok</info>'));
    }
}
