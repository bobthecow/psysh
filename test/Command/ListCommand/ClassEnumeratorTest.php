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

use Psy\Command\ListCommand\ClassEnumerator;
use Psy\Reflection\ReflectionNamespace;
use Psy\Test\Fixtures\Command\ListCommand\ClassAlfa;
use Psy\Test\Fixtures\Command\ListCommand\ClassBravo;
use Psy\Test\Fixtures\Command\ListCommand\ClassCharlie;
use Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta;
use Psy\Test\Fixtures\Command\ListCommand\InterfaceEcho;
use Psy\Test\Fixtures\Command\ListCommand\TraitFoxtrot;
use Psy\Test\Fixtures\Command\ListCommand\TraitGolf;

/**
 * @group isolation-fail
 */
class ClassEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes');
        $target = new ClassAlfa();

        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(InterfaceDelta::class), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(TraitFoxtrot::class), $target));
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
                'value' => 'class <class>Psy\Test\Fixtures\Command\ListCommand\ClassAlfa</class>',
            ],
            ClassBravo::class => [
                'name'  => ClassBravo::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Fixtures\Command\ListCommand\ClassBravo</class> '.
                    'implements <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta</class>',
            ],
            ClassCharlie::class => [
                'name'  => ClassCharlie::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Fixtures\Command\ListCommand\ClassCharlie</class> '.
                    'extends <class>Psy\Test\Fixtures\Command\ListCommand\ClassBravo</class> '.
                    'implements <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta</class>',
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

        $prefix = \PHP_VERSION_ID === 70400 ? '<keyword>static</keyword> ' : '';

        $expected = [
            InterfaceDelta::class => [
                'name'  => InterfaceDelta::class,
                'style' => 'class',
                'value' => 'interface <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta</class>',
            ],
            InterfaceEcho::class => [
                'name'  => InterfaceEcho::class,
                'style' => 'class',
                'value' => $prefix.'interface <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceEcho</class> '.
                    'extends <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta</class>',
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
            TraitFoxtrot::class => [
                'name'  => TraitFoxtrot::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Fixtures\Command\ListCommand\TraitFoxtrot</class>',
            ],
            TraitGolf::class => [
                'name'  => TraitGolf::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Fixtures\Command\ListCommand\TraitGolf</class>',
            ],
        ];

        $this->assertSame($expected, $fixtureClasses);
    }

    public function testEnumerateNamespace()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes --interfaces --traits');

        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Fixtures\\Command\\ListCommand'), null);

        $expectedClasses = [
            ClassAlfa::class => [
                'name'  => ClassAlfa::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Fixtures\Command\ListCommand\ClassAlfa</class>',
            ],
            ClassBravo::class => [
                'name'  => ClassBravo::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Fixtures\Command\ListCommand\ClassBravo</class> '.
                    'implements <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta</class>',
            ],
            ClassCharlie::class => [
                'name'  => ClassCharlie::class,
                'style' => 'class',
                'value' => 'class <class>Psy\Test\Fixtures\Command\ListCommand\ClassCharlie</class> '.
                    'extends <class>Psy\Test\Fixtures\Command\ListCommand\ClassBravo</class> '.
                    'implements <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta</class>',
            ],
        ];

        $this->assertArrayHasKey('Classes', $res);
        $this->assertSame($expectedClasses, $res['Classes']);

        $prefix = \PHP_VERSION_ID === 70400 ? '<keyword>static</keyword> ' : '';
        $expectedInterfaces = [
            InterfaceDelta::class => [
                'name'  => InterfaceDelta::class,
                'style' => 'class',
                'value' => 'interface <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta</class>',
            ],
            InterfaceEcho::class => [
                'name'  => InterfaceEcho::class,
                'style' => 'class',
                'value' => $prefix.'interface <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceEcho</class> '.
                    'extends <class>Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta</class>',
            ],
        ];
        $this->assertArrayHasKey('Interfaces', $res);
        $this->assertSame($expectedInterfaces, $res['Interfaces']);

        $expectedTraits = [
            TraitFoxtrot::class => [
                'name'  => TraitFoxtrot::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Fixtures\Command\ListCommand\TraitFoxtrot</class>',
            ],
            TraitGolf::class => [
                'name'  => TraitGolf::class,
                'style' => 'class',
                'value' => 'trait <class>Psy\Test\Fixtures\Command\ListCommand\TraitGolf</class>',
            ],
        ];
        $this->assertArrayHasKey('Traits', $res);
        $this->assertSame($expectedTraits, $res['Traits']);
    }

    public function testEnumerateParentNamespace()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes --interfaces --traits');

        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Fixtures\\Command'), null);

        $this->assertArrayHasKey('Classes', $res);
        $this->assertArrayHasKey('Interfaces', $res);
        $this->assertArrayHasKey('Traits', $res);

        foreach ([ClassAlfa::class, ClassBravo::class, ClassCharlie::class] as $className) {
            $this->assertArrayHasKey($className, $res['Classes']);
        }
        $this->assertGreaterThanOrEqual(3, \count($res['Classes']));

        foreach ([InterfaceDelta::class, InterfaceEcho::class] as $interfaceName) {
            $this->assertArrayHasKey($interfaceName, $res['Interfaces']);
        }
        $this->assertGreaterThanOrEqual(2, \count($res['Interfaces']));

        foreach ([TraitFoxtrot::class, TraitGolf::class] as $traitName) {
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

        foreach ([ClassAlfa::class, self::class, 'Psy\\Shell'] as $className) {
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

        foreach ([ClassAlfa::class, self::class, 'Psy\\Shell'] as $className) {
            $this->assertArrayNotHasKey($className, $res['Internal Classes']);
        }
    }

    public function testNamespacedInternalAndUserClasses()
    {
        $enumerator = new ClassEnumerator($this->getPresenter());
        $input = $this->getInput('--classes --internal --user');

        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Fixtures\\Command\\ListCommand'), null);

        $this->assertArrayHasKey('User Classes', $res);
        $this->assertArrayHasKey(ClassAlfa::class, $res['User Classes']);

        $this->assertArrayNotHasKey('Internal Classes', $res);
    }

    private function isFixtureClass($info)
    {
        return \strpos($info['name'], '\\Fixtures\\Command\\ListCommand\\') !== false;
    }
}
