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

use Psy\Command\ListCommand\ClassEnumerator;
use Psy\Reflection\ReflectionNamespace;

/**
 * @group isolation-fail
 */
class ClassEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes');
        $target = new Fixtures\ClassAlfa();

        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\InterfaceDelta::class), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\TraitFoxtrot::class), $target));
    }

    public function testEnumerateClasses()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes');
        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('Classes', $res);
        $fixtureClasses = \array_filter($res['Classes'], [$this, 'isFixtureClass']);

        $expected = [
            Fixtures\ClassAlfa::class => [
                'name'  => Fixtures\ClassAlfa::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassAlfa</class>',
            ],
            Fixtures\ClassBravo::class => [
                'name'  => Fixtures\ClassBravo::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassBravo</class> '.
                    'implements <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
            Fixtures\ClassCharlie::class => [
                'name'  => Fixtures\ClassCharlie::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassCharlie</class> '.
                    'extends <class>Psy\Test\Command\ListCommand\Fixtures\ClassBravo</class> '.
                    'implements <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
        ];

        $this->assertSame($expected, $fixtureClasses);
    }

    public function testEnumerateInterfaces()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--interfaces');
        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('Interfaces', $res);
        $fixtureClasses = \array_filter($res['Interfaces'], [$this, 'isFixtureClass']);

        $prefix = \PHP_VERSION === '7.4.0' ? '<keyword>static</keyword> ' : '';

        $expected = [
            Fixtures\InterfaceDelta::class => [
                'name'  => Fixtures\InterfaceDelta::class,
                'style' => 'class',
                'value' => 'interface <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
            Fixtures\InterfaceEcho::class => [
                'name'  => Fixtures\InterfaceEcho::class,
                'style' => 'class',
                'value' => $prefix.'interface <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho</class> '.
                    'extends <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
        ];

        $this->assertSame($expected, $fixtureClasses);
    }

    public function testEnumerateTraits()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--traits');
        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('Traits', $res);
        $fixtureClasses = \array_filter($res['Traits'], [$this, 'isFixtureClass']);

        $expected = [
            Fixtures\TraitFoxtrot::class => [
                'name'  => Fixtures\TraitFoxtrot::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot</class>',
            ],
            Fixtures\TraitGolf::class => [
                'name'  => Fixtures\TraitGolf::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Command\ListCommand\Fixtures\TraitGolf</class>',
            ],
        ];

        $this->assertSame($expected, $fixtureClasses);
    }

    public function testEnumerateNamespace()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes --interfaces --traits');

        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Command\\ListCommand\\Fixtures'), null);

        $expectedClasses = [
            Fixtures\ClassAlfa::class => [
                'name'  => Fixtures\ClassAlfa::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassAlfa</class>',
            ],
            Fixtures\ClassBravo::class => [
                'name'  => Fixtures\ClassBravo::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassBravo</class> '.
                    'implements <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
            Fixtures\ClassCharlie::class => [
                'name'  => Fixtures\ClassCharlie::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Command\ListCommand\Fixtures\ClassCharlie</class> '.
                    'extends <class>Psy\Test\Command\ListCommand\Fixtures\ClassBravo</class> '.
                    'implements <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
        ];

        $this->assertArrayHasKey('Classes', $res);
        $this->assertSame($expectedClasses, $res['Classes']);

        $prefix = \PHP_VERSION === '7.4.0' ? '<keyword>static</keyword> ' : '';
        $expectedInterfaces = [
            Fixtures\InterfaceDelta::class => [
                'name'  => Fixtures\InterfaceDelta::class,
                'style' => 'class',
                'value' => 'interface <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
            Fixtures\InterfaceEcho::class => [
                'name'  => Fixtures\InterfaceEcho::class,
                'style' => 'class',
                'value' => $prefix.'interface <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho</class> '.
                    'extends <class>Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta</class>',
            ],
        ];
        $this->assertArrayHasKey('Interfaces', $res);
        $this->assertSame($expectedInterfaces, $res['Interfaces']);

        $expectedTraits = [
            Fixtures\TraitFoxtrot::class => [
                'name'  => Fixtures\TraitFoxtrot::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot</class>',
            ],
            Fixtures\TraitGolf::class => [
                'name'  => Fixtures\TraitGolf::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Command\ListCommand\Fixtures\TraitGolf</class>',
            ],
        ];
        $this->assertArrayHasKey('Traits', $res);
        $this->assertSame($expectedTraits, $res['Traits']);
    }

    public function testEnumerateParentNamespace()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes --interfaces --traits');

        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Command\\ListCommand'), null);

        $this->assertArrayHasKey('Classes', $res);
        $this->assertArrayHasKey('Interfaces', $res);
        $this->assertArrayHasKey('Traits', $res);

        foreach ([Fixtures\ClassAlfa::class, Fixtures\ClassBravo::class, Fixtures\ClassCharlie::class, self::class] as $className) {
            $this->assertArrayHasKey($className, $res['Classes']);
        }
        $this->assertGreaterThanOrEqual(4, \count($res['Classes']));

        foreach ([Fixtures\InterfaceDelta::class, Fixtures\InterfaceEcho::class] as $interfaceName) {
            $this->assertArrayHasKey($interfaceName, $res['Interfaces']);
        }
        $this->assertGreaterThanOrEqual(2, \count($res['Interfaces']));

        foreach ([Fixtures\TraitFoxtrot::class, Fixtures\TraitGolf::class] as $traitName) {
            $this->assertArrayHasKey($traitName, $res['Traits']);
        }
        $this->assertGreaterThanOrEqual(2, \count($res['Traits']));
    }

    public function testEnumerateUserClasses()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes --user');

        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('User Classes', $res);
        $this->assertArrayNotHasKey('Internal Classes', $res);

        foreach ([Fixtures\ClassAlfa::class, self::class, 'Psy\\Shell'] as $className) {
            $this->assertArrayHasKey($className, $res['User Classes']);
        }

        foreach (['stdClass', 'DateTime'] as $className) {
            $this->assertArrayNotHasKey($className, $res['User Classes']);
        }
    }

    public function testEnumerateInternalClasses()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes --internal');

        $res = $enumerator->enumerate($input, null, null);

        $this->assertArrayHasKey('Internal Classes', $res);
        $this->assertArrayNotHasKey('User Classes', $res);

        foreach (['stdClass', 'DateTime'] as $className) {
            $this->assertArrayHasKey($className, $res['Internal Classes']);
        }

        foreach ([Fixtures\ClassAlfa::class, self::class, 'Psy\\Shell'] as $className) {
            $this->assertArrayNotHasKey($className, $res['Internal Classes']);
        }
    }

    public function testNamespacedInternalAndUserClasses()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes --internal --user');

        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Command\\ListCommand\\Fixtures'), null);

        $this->assertArrayHasKey('User Classes', $res);
        $this->assertArrayHasKey(Fixtures\ClassAlfa::class, $res['User Classes']);

        $this->assertArrayNotHasKey('Internal Classes', $res);
    }

    private function isFixtureClass($info)
    {
        return \strpos($info['name'], '\\ListCommand\\Fixtures\\') !== false;
    }
}
