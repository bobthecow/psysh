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

use Psy\Command\ListCommand\ClassEnumerator;

class ClassEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes');
        $target = new Fixtures\ClassAlfa();

        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta'), $target));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot'), $target));
    }

    public function testEnumerateClasses()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes');
        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('Classes', $res);
        $fixtureClasses = \array_filter($res['Classes'], [$this, 'isFixtureClass']);

        $expected = [
            'Psy\Test\Command\ListCommand\Fixtures\ClassAlfa' => [
                'name'  => 'Psy\Test\Command\ListCommand\Fixtures\ClassAlfa',
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassAlfa</class>',
            ],
            'Psy\Test\Command\ListCommand\Fixtures\ClassBravo' => [
                'name'  => 'Psy\Test\Command\ListCommand\Fixtures\ClassBravo',
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassBravo</class> ' .
                    'implements <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
            'Psy\Test\Command\ListCommand\Fixtures\ClassCharlie' => [
                'name'  => 'Psy\Test\Command\ListCommand\Fixtures\ClassCharlie',
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

        $expected = [
            'Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta' => [
                'name'  => 'Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta',
                'style' => 'class',
                'value' => 'interface <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
            'Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho' => [
                'name'  => 'Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho',
                'style' => 'class',
                'value' => 'interface <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho</class> ' .
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
            'Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot' => [
                'name'  => 'Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot',
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot</class>',
            ],
            'Psy\Test\Command\ListCommand\Fixtures\TraitGolf' => [
                'name'  => 'Psy\Test\Command\ListCommand\Fixtures\TraitGolf',
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
