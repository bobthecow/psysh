<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand;

use Psy\Command\ListCommand\FunctionEnumerator;
use Psy\Formatter\SignatureFormatter;
use Psy\Reflection\ReflectionNamespace;
use Psy\Test\Fixtures\Command\ListCommand\ClassAlfa;
use Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta;
use Psy\Test\Fixtures\Command\ListCommand\TraitFoxtrot;

require_once __DIR__.'/../../Fixtures/Command/ListCommand/functions.php';

/**
 * @group isolation-fail
 */
class FunctionEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingWithoutFlag()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('');
        $this->assertSame([], $enumerator->enumerate($input));
    }

    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('--functions');
        $target = new ClassAlfa();

        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(InterfaceDelta::class), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(TraitFoxtrot::class), $target));
    }

    public function testEnumerateInternalFunctions()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('--functions --internal');

        $res = $enumerator->enumerate($input);

        $this->assertArrayHasKey('Internal Functions', $res);
        $functions = $res['Internal Functions'];

        $unexpected = ['composer\\autoload\\includefile', 'dump', 'psy\\sh', 'psy\\debug', 'psy\\info', 'psy\\bin'];
        foreach ($unexpected as $name) {
            $this->assertArrayNotHasKey($name, $functions);
        }

        $expected = ['array_push', 'array_pop', 'json_encode', 'htmlspecialchars'];
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $functions);
            $signature = SignatureFormatter::format(new \ReflectionFunction($name));
            $this->assertSame(['name' => $name, 'style' => 'function', 'value' => $signature], $functions[$name]);
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

        $expected = ['dump', 'psy\\sh', 'psy\\debug', 'psy\\info', 'psy\\bin'];
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $functions);
            $signature = SignatureFormatter::format(new \ReflectionFunction($name));
            $this->assertSame(['name' => $name, 'style' => 'function', 'value' => $signature], $functions[$name]);
        }
    }

    public function testEnumerateNamespaceFunctions()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('--functions');
        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Fixtures\\Command\\ListCommand'));

        $this->assertArrayHasKey('Functions', $res);

        $expected = [
            'psy\\test\\fixtures\\command\\listcommand\\bar' => [
                'name'  => 'psy\\test\\fixtures\\command\\listcommand\\bar',
                'style' => 'function',
                'value' => '<keyword>function</keyword> <function>Psy\\Test\\Fixtures\\Command\\ListCommand\\bar</function>()',
            ],
            'psy\\test\\fixtures\\command\\listcommand\\foo' => [
                'name'  => 'psy\\test\\fixtures\\command\\listcommand\\foo',
                'style' => 'function',
                'value' => '<keyword>function</keyword> <function>Psy\\Test\\Fixtures\\Command\\ListCommand\\foo</function>()',
            ],
        ];

        $this->assertSame($expected, $res['Functions']);
    }

    public function testEnumerateUserAndInternalNamespaceFunctions()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('--functions --user --internal');
        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Fixtures\\Command\\ListCommand'));

        $this->assertArrayHasKey('User Functions', $res);
        $this->assertArrayNotHasKey('Internal Functions', $res);

        $expected = [
            'psy\\test\\fixtures\\command\\listcommand\\bar' => [
                'name'  => 'psy\\test\\fixtures\\command\\listcommand\\bar',
                'style' => 'function',
                'value' => '<keyword>function</keyword> <function>Psy\\Test\\Fixtures\\Command\\ListCommand\\bar</function>()',
            ],
            'psy\\test\\fixtures\\command\\listcommand\\foo' => [
                'name'  => 'psy\\test\\fixtures\\command\\listcommand\\foo',
                'style' => 'function',
                'value' => '<keyword>function</keyword> <function>Psy\\Test\\Fixtures\\Command\\ListCommand\\foo</function>()',
            ],
        ];

        $this->assertSame($expected, $res['User Functions']);
    }
}
