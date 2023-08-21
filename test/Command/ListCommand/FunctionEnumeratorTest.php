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

use Psy\Command\ListCommand\FunctionEnumerator;
use Psy\Formatter\SignatureFormatter;
use Psy\Reflection\ReflectionNamespace;

require_once __DIR__.'/Fixtures/functions.php';

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
        $target = new Fixtures\ClassAlfa();

        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\InterfaceDelta::class), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\TraitFoxtrot::class), $target));
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
        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Command\\ListCommand\\Fixtures'));

        $this->assertArrayHasKey('Functions', $res);

        $expected = [
            'psy\\test\\command\\listcommand\\fixtures\\bar' => [
                'name'  => 'psy\\test\\command\\listcommand\\fixtures\\bar',
                'style' => 'function',
                'value' => '<keyword>function</keyword> <function>Psy\\Test\\Command\\ListCommand\\Fixtures\\bar</function>()',
            ],
            'psy\\test\\command\\listcommand\\fixtures\\foo' => [
                'name'  => 'psy\\test\\command\\listcommand\\fixtures\\foo',
                'style' => 'function',
                'value' => '<keyword>function</keyword> <function>Psy\\Test\\Command\\ListCommand\\Fixtures\\foo</function>()',
            ],
        ];

        $this->assertSame($expected, $res['Functions']);
    }

    public function testEnumerateUserAndInternalNamespaceFunctions()
    {
        $enumerator = new FunctionEnumerator($this->getPresenter());
        $input = $this->getInput('--functions --user --internal');
        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Command\\ListCommand\\Fixtures'));

        $this->assertArrayHasKey('User Functions', $res);
        $this->assertArrayNotHasKey('Internal Functions', $res);

        $expected = [
            'psy\\test\\command\\listcommand\\fixtures\\bar' => [
                'name'  => 'psy\\test\\command\\listcommand\\fixtures\\bar',
                'style' => 'function',
                'value' => '<keyword>function</keyword> <function>Psy\\Test\\Command\\ListCommand\\Fixtures\\bar</function>()',
            ],
            'psy\\test\\command\\listcommand\\fixtures\\foo' => [
                'name'  => 'psy\\test\\command\\listcommand\\fixtures\\foo',
                'style' => 'function',
                'value' => '<keyword>function</keyword> <function>Psy\\Test\\Command\\ListCommand\\Fixtures\\foo</function>()',
            ],
        ];

        $this->assertSame($expected, $res['User Functions']);
    }
}
