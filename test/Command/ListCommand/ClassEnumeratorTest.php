<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand;

use Psy\Command\ListCommand\ClassEnumerator;
use Psy\Test\Command\ListCommand\Fixtures\ClassAlfa;
use Psy\Test\Command\ListCommand\Fixtures\ClassBravo;
use Psy\Test\Command\ListCommand\Fixtures\ClassCharlie;
use Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta;
use Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho;
use Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot;
use Psy\Test\Command\ListCommand\Fixtures\TraitGolf;

class ClassEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes');
        $target = new Fixtures\ClassAlfa();

        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass(InterfaceDelta::class), $target));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass(TraitFoxtrot::class), $target));
    }

    public function testEnumerateClasses()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes');
        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('Classes', $res);
        $fixtureClasses = \array_filter($res['Classes'], [$this, 'isFixtureClass']);

        $expected = [
            ClassAlfa::class => [
                'name'  => ClassAlfa::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassAlfa</class>',
            ],
            ClassBravo::class => [
                'name'  => ClassBravo::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassBravo</class> ' .
                    'implements <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
            ClassCharlie::class => [
                'name'  => ClassCharlie::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassCharlie</class> ' .
                    'extends <class>Psy\Test\Command\ListCommand\Fixtures\ClassBravo</class> ' .
                    'implements <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
        ];

        $this->assertEquals($expected, $fixtureClasses);
    }

    public function testEnumerateInterfaces()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--interfaces');
        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('Interfaces', $res);
        $fixtureClasses = \array_filter($res['Interfaces'], [$this, 'isFixtureClass']);

        $prefix = PHP_VERSION === '7.4.0' ? '<keyword>static</keyword> ' : '';

        $expected = [
            InterfaceDelta::class => [
                'name'  => InterfaceDelta::class,
                'style' => 'class',
                'value' => 'interface <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
            InterfaceEcho::class => [
                'name'  => InterfaceEcho::class,
                'style' => 'class',
                'value' => $prefix . 'interface <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho</class> ' .
                    'extends <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
        ];

        $this->assertEquals($expected, $fixtureClasses);
    }

    public function testEnumerateTraits()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--traits');
        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('Traits', $res);
        $fixtureClasses = \array_filter($res['Traits'], [$this, 'isFixtureClass']);

        $expected = [
            TraitFoxtrot::class => [
                'name'  => TraitFoxtrot::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot</class>',
            ],
            TraitGolf::class => [
                'name'  => TraitGolf::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Command\ListCommand\Fixtures\TraitGolf</class>',
            ],
        ];

        $this->assertEquals($expected, $fixtureClasses);
    }

    private function isFixtureClass($info)
    {
        return \strpos($info['name'], '\\ListCommand\\Fixtures\\') !== false;
    }
}
