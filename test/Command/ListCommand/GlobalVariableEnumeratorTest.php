<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand;

use Psy\Command\ListCommand\GlobalVariableEnumerator;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * @group isolation-fail
 */
class GlobalVariableEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingWithoutFlag()
    {
        $enumerator = new GlobalVariableEnumerator($this->getPresenter());
        $input = $this->getInput('');
        $this->assertSame([], $enumerator->enumerate($input));
    }

    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new GlobalVariableEnumerator($this->getPresenter());
        $input = $this->getInput('--globals');
        $target = new Fixtures\ClassAlfa();

        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\InterfaceDelta::class), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\TraitFoxtrot::class), $target));
    }

    public function testEnumerate()
    {
        $one = 'psyTestGlobal'.\rand();
        $GLOBALS[$one] = 42;

        $two = 'psyTestGlobal'.\rand();
        $GLOBALS[$two] = 'string';

        $three = 'psyTestGlobal'.\rand();
        $GLOBALS[$three] = [];

        $enumerator = new GlobalVariableEnumerator($this->getPresenter());
        $input = $this->getInput('--globals');

        $res = $enumerator->enumerate($input);

        // Clean up before asserting anything
        unset($GLOBALS[$one]);
        unset($GLOBALS[$two]);
        unset($GLOBALS[$three]);

        $this->assertArrayHasKey('Global Variables', $res);
        $globals = $res['Global Variables'];

        $name = '$'.$one;
        $style = 'global';
        $value = $this->presentNumber(42);
        $this->assertArrayHasKey('$'.$one, $globals);
        $this->assertSame(\compact('name', 'style', 'value'), $globals[$name]);

        $name = '$'.$two;
        $style = 'global';
        $value = OutputFormatter::escape('"<string>string</string>"');
        $this->assertArrayHasKey('$'.$two, $globals);
        $this->assertSame(\compact('name', 'style', 'value'), $globals[$name]);

        $name = '$'.$three;
        $style = 'global';
        $value = '[]';
        $this->assertArrayHasKey('$'.$three, $globals);
        $this->assertSame(\compact('name', 'style', 'value'), $globals[$name]);
    }
}
