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

use Psy\Command\ListCommand\MethodEnumerator;

class MethodEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingWithoutFlag()
    {
        $enumerator = new MethodEnumerator($this->getPresenter());
        $input = $this->getInput('');
        $this->assertSame([], $enumerator->enumerate($input));
    }

    public function testEnumerateReturnsNothingWithoutTarget()
    {
        $enumerator = new MethodEnumerator($this->getPresenter());
        $input = $this->getInput('--methods');

        $this->assertSame([], $enumerator->enumerate($input, null, null));
    }

    public function testEnumeratePublicMethods()
    {
        $enumerator = new MethodEnumerator($this->getPresenter());
        $input = $this->getInput('--methods');
        $target = new Fixtures\ClassAlfa();

        $res = $enumerator->enumerate($input, new \ReflectionClass($target), null);

        $this->assertArrayHasKey('Class Methods', $res);
        $methods = $res['Class Methods'];

        $this->assertSame([
            'foo' => [
                'name'  => 'foo',
                'style' => 'public',
                'value' => '<keyword>public</keyword> <keyword>function</keyword> <function>foo</function>()',
            ],
        ], $methods);
    }

    public function testEnumerateAllMethods()
    {
        $enumerator = new MethodEnumerator($this->getPresenter());
        $input = $this->getInput('--methods --all');
        $target = new Fixtures\ClassAlfa();

        $res = $enumerator->enumerate($input, new \ReflectionClass($target), null);

        $this->assertArrayHasKey('Class Methods', $res);
        $methods = $res['Class Methods'];

        $this->assertEquals([
            'foo' => [
                'name'  => 'foo',
                'style' => 'public',
                'value' => '<keyword>public</keyword> <keyword>function</keyword> <function>foo</function>()',
            ],
            'bar' => [
                'name'  => 'bar',
                'style' => 'protected',
                'value' => '<keyword>protected</keyword> <keyword>function</keyword> <function>bar</function>()',
            ],
            'baz' => [
                'name'  => 'baz',
                'style' => 'private',
                'value' => '<keyword>private</keyword> <keyword>function</keyword> <function>baz</function>()',
            ],
        ], $methods);
    }

    public function testEnumerateInheritedMethods()
    {
        $enumerator = new MethodEnumerator($this->getPresenter());
        $input = $this->getInput('--methods --all');
        $target = new Fixtures\ClassCharlie();

        $res = $enumerator->enumerate($input, new \ReflectionClass($target), null);

        $this->assertArrayHasKey('Class Methods', $res);
        $methods = $res['Class Methods'];

        $this->assertEquals([
            'foo' => [
                'name'  => 'foo',
                'style' => 'public',
                'value' => '<keyword>public</keyword> <keyword>function</keyword> <function>foo</function>()',
            ],
            'bar' => [
                'name'  => 'bar',
                'style' => 'protected',
                'value' => '<keyword>protected</keyword> <keyword>function</keyword> <function>bar</function>()',
            ],
            'qux' => [
                'name'  => 'qux',
                'style' => 'public',
                'value' => '<keyword>public</keyword> <keyword>function</keyword> <function>qux</function>()',
            ],
        ], $methods);
    }

    public function testEnumerateNonInheritedMethods()
    {
        $enumerator = new MethodEnumerator($this->getPresenter());
        $input = $this->getInput('--methods --no-inherit');
        $target = new Fixtures\ClassCharlie();

        $res = $enumerator->enumerate($input, new \ReflectionClass($target), null);

        $this->assertArrayHasKey('Class Methods', $res);
        $methods = $res['Class Methods'];

        $this->assertSame([
            'qux' => [
                'name'  => 'qux',
                'style' => 'public',
                'value' => '<keyword>public</keyword> <keyword>function</keyword> <function>qux</function>()',
            ],
        ], $methods);
    }

    public function testInterfaceMethods()
    {
        $enumerator = new MethodEnumerator($this->getPresenter());
        $input = $this->getInput('--methods');

        $res = $enumerator->enumerate($input, new \ReflectionClass(Fixtures\InterfaceEcho::class), null);

        $this->assertArrayHasKey('Interface Methods', $res);
        $methods = $res['Interface Methods'];

        $this->assertSame([
            'doEcho' => [
                'name'  => 'doEcho',
                'style' => 'public',
                'value' => '<keyword>abstract</keyword> <keyword>public</keyword> <keyword>function</keyword> <function>doEcho</function>()',
            ],
        ], $methods);
    }

    public function testTraitMethods()
    {
        $enumerator = new MethodEnumerator($this->getPresenter());
        $input = $this->getInput('--methods');

        $res = $enumerator->enumerate($input, new \ReflectionClass(Fixtures\TraitFoxtrot::class), null);

        $this->assertArrayHasKey('Trait Methods', $res);
        $methods = $res['Trait Methods'];

        $this->assertSame([
            'doFoxtrot' => [
                'name'  => 'doFoxtrot',
                'style' => 'public',
                'value' => '<keyword>public</keyword> <keyword>function</keyword> <function>doFoxtrot</function>()',
            ],
        ], $methods);
    }
}
