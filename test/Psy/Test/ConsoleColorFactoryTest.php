<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Configuration;
use Psy\ConsoleColorFactory;

class ConsoleColorFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testGetConsoleColorAuto()
    {
        $colorMode = Configuration::COLOR_MODE_AUTO;
        $factory = new ConsoleColorFactory($colorMode);
        $colors = $factory->getConsoleColor();
        $themes = $colors->getThemes();

        $this->assertFalse($colors->isStyleForced());
        $this->assertEquals(array('blue'), $themes['line_number']);
    }

    public function testGetConsoleColorForced()
    {
        $colorMode = Configuration::COLOR_MODE_FORCED;
        $factory = new ConsoleColorFactory($colorMode);
        $colors = $factory->getConsoleColor();
        $themes = $colors->getThemes();

        $this->assertTrue($colors->isStyleForced());
        $this->assertEquals(array('blue'), $themes['line_number']);
    }

    public function testGetConsoleColorDisabled()
    {
        $colorMode = Configuration::COLOR_MODE_DISABLED;
        $factory = new ConsoleColorFactory($colorMode);
        $colors = $factory->getConsoleColor();
        $themes = $colors->getThemes();

        $this->assertFalse($colors->isStyleForced());
        $this->assertEquals(array('none'), $themes['line_number']);
    }
}
