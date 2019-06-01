<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2019 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand;

use Psy\Command\ListCommand\FunctionEnumerator;
use Psy\Formatter\SignatureFormatter;

class FunctionEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingWithoutFlag()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('');
        $this->assertEquals([], $enumerator->enumerate($input));
    }

    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('--functions');
        $target = new Fixtures\ClassAlfa();

        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta'), $target));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot'), $target));
    }

    public function testEnumerateInternalFunctions()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('--functions --internal');

        $res = $enumerator->enumerate($input);

        $this->assertArrayHasKey('Internal Functions', $res);
        $functions = $res['Internal Functions'];

        $unexpected = ['composer\autoload\includefile', 'dump', 'psy\sh', 'psy\debug', 'psy\info', 'psy\bin'];
        foreach ($unexpected as $name) {
            $this->assertArrayNotHasKey($name, $functions);
        }

        $expected = ['array_push', 'array_pop', 'json_encode', 'htmlspecialchars'];
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $functions);
            $signature = SignatureFormatter::format(new \ReflectionFunction($name));
            $this->assertEquals(['name' => $name, 'style' => 'function', 'value' => $signature], $functions[$name]);
        }
    }

    public function testEnumerateUserFunctions()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('--functions --user');

        $res = $enumerator->enumerate($input);

        $this->assertArrayHasKey('User Functions', $res);
        $functions = $res['User Functions'];

        $unexpected = ['array_push', 'array_pop', 'json_encode', 'htmlspecialchars'];
        foreach ($unexpected as $name) {
            $this->assertArrayNotHasKey($name, $functions);
        }

        $expected = ['composer\autoload\includefile', 'dump', 'psy\sh', 'psy\debug', 'psy\info', 'psy\bin'];
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $functions);
            $signature = SignatureFormatter::format(new \ReflectionFunction($name));
            $this->assertEquals(['name' => $name, 'style' => 'function', 'value' => $signature], $functions[$name]);
        }
    }
}
