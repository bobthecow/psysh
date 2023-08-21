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

use Psy\Command\ListCommand\PropertyEnumerator;

/**
 * @group isolation-fail
 */
class PropertyEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingWithoutFlag()
    {
        $enumerator = new PropertyEnumerator($this->getPresenter());
        $input = $this->getInput('');
        $this->assertSame([], $enumerator->enumerate($input));
    }

    public function testEnumerateReturnsNothingWithoutTarget()
    {
        $enumerator = new PropertyEnumerator($this->getPresenter());
        $input = $this->getInput('--properties');

        $this->assertSame([], $enumerator->enumerate($input, null, null));
    }

    public function testEnumeratePublicProperties()
    {
        $enumerator = new PropertyEnumerator($this->getPresenter());
        $input = $this->getInput('--properties');
        $target = new Fixtures\ClassAlfa();

        $res = $enumerator->enumerate($input, new \ReflectionClass($target), null);

        $this->assertArrayHasKey('Class Properties', $res);
        $properties = $res['Class Properties'];

        $this->assertSame([
            '$foo' => [
                'name'  => '$foo',
                'style' => 'public',
                'value' => '',
            ],
        ], $properties);
    }

    public function testEnumerateAllProperties()
    {
        $enumerator = new PropertyEnumerator($this->getPresenter());
        $input = $this->getInput('--properties --all');
        $target = new Fixtures\ClassAlfa();

        $res = $enumerator->enumerate($input, new \ReflectionClass($target), null);

        $this->assertArrayHasKey('Class Properties', $res);
        $properties = $res['Class Properties'];

        $this->assertEquals([
            '$foo' => [
                'name'  => '$foo',
                'style' => 'public',
                'value' => '',
            ],
            '$bar' => [
                'name'  => '$bar',
                'style' => 'protected',
                'value' => '',
            ],
            '$baz' => [
                'name'  => '$baz',
                'style' => 'private',
                'value' => '',
            ],
        ], $properties);
    }

    public function testEnumerateInheritedProperties()
    {
        $enumerator = new PropertyEnumerator($this->getPresenter());
        $input = $this->getInput('--properties --all');
        $target = new Fixtures\ClassCharlie();

        $res = $enumerator->enumerate($input, new \ReflectionClass($target), null);

        $this->assertArrayHasKey('Class Properties', $res);
        $properties = $res['Class Properties'];

        $this->assertEquals([
            '$foo' => [
                'name'  => '$foo',
                'style' => 'public',
                'value' => '',
            ],
            '$bar' => [
                'name'  => '$bar',
                'style' => 'protected',
                'value' => '',
            ],
            '$qux' => [
                'name'  => '$qux',
                'style' => 'public',
                'value' => '',
            ],
        ], $properties);
    }

    public function testEnumerateNonInheritedProperties()
    {
        $enumerator = new PropertyEnumerator($this->getPresenter());
        $input = $this->getInput('--properties --no-inherit');
        $target = new Fixtures\ClassCharlie();

        $res = $enumerator->enumerate($input, new \ReflectionClass($target), null);

        $this->assertArrayHasKey('Class Properties', $res);
        $properties = $res['Class Properties'];

        $this->assertSame([
            '$qux' => [
                'name'  => '$qux',
                'style' => 'public',
                'value' => '',
            ],
        ], $properties);
    }

    public function testInterfacePropertiesDoNotExist()
    {
        $enumerator = new PropertyEnumerator($this->getPresenter());
        $input = $this->getInput('--properties');

        $res = $enumerator->enumerate($input, new \ReflectionClass(Fixtures\InterfaceEcho::class), null);
        $this->assertSame([], $res);
    }

    public function testTraitProperties()
    {
        $enumerator = new PropertyEnumerator($this->getPresenter());
        $input = $this->getInput('--properties');

        $res = $enumerator->enumerate($input, new \ReflectionClass(Fixtures\TraitFoxtrot::class), null);

        $this->assertArrayHasKey('Trait Properties', $res);
        $properties = $res['Trait Properties'];

        $this->assertSame([
            '$someFoxtrot' => [
                'name'  => '$someFoxtrot',
                'style' => 'public',
                'value' => '',
            ],
        ], $properties);
    }
}
