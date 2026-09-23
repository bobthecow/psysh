<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Sudo;
use Psy\Test\Fixtures\ClassWithSecretConstructor;
use Psy\Test\Fixtures\ClassWithSecretiveParent;
use Psy\Test\Fixtures\ClassWithSecretParentConstructor;
use Psy\Test\Fixtures\ClassWithSecrets;

class SudoTest extends TestCase
{
    public function testFetchProperty()
    {
        $obj = new ClassWithSecrets();
        $this->assertSame('private and prop', Sudo::fetchProperty($obj, 'privateProp'));
    }

    public function testAssignProperty()
    {
        $obj = new ClassWithSecrets();
        $this->assertSame('private and prop', Sudo::fetchProperty($obj, 'privateProp'));
        $this->assertSame('not so private now', Sudo::assignProperty($obj, 'privateProp', 'not so private now'));
        $this->assertSame('not so private now', Sudo::fetchProperty($obj, 'privateProp'));
    }

    public function testCallMethod()
    {
        $obj = new ClassWithSecrets();
        $this->assertSame('private and method', Sudo::callMethod($obj, 'privateMethod'));
        $this->assertSame('private and method with 1', Sudo::callMethod($obj, 'privateMethod', 1));
        $this->assertSame(
            'private and method with ["foo",2]',
            Sudo::callMethod($obj, 'privateMethod', ['foo', 2]
            ));
    }

    public function testCallStaticMethodThroughObject()
    {
        $this->assertSame('private and static and method with 1', Sudo::callMethod(new ClassWithSecrets(), 'privateStaticMethod', 1));
    }

    public function testGetMethodClosure()
    {
        $object = new ClassWithSecretiveParent();
        $closure = Sudo::getMethodClosure($object, 'privateMethod');

        $this->assertSame('private and method with 1', $closure(1));
        $this->assertSame('extra', (new \ReflectionFunction($closure))->getParameters()[0]->getName());
    }

    public function testGetStaticMethodClosure()
    {
        foreach ([ClassWithSecretiveParent::class, new ClassWithSecretiveParent()] as $class) {
            $closure = Sudo::getStaticMethodClosure($class, 'privateStaticMethod');

            $this->assertSame('private and static and method with 1', $closure(1));
            $this->assertSame('extra', (new \ReflectionFunction($closure))->getParameters()[0]->getName());
        }
    }

    public function testInternalMethodClosure()
    {
        $closure = Sudo::getMethodClosure(new \SplMaxHeap(), 'compare');

        $this->assertSame(1, $closure(2, 1));
    }

    public function testMethodClosurePreservesReferencesAndSignature()
    {
        $object = new class() {
            private function &increment(int &$value, int $amount = 1): int
            {
                $value += $amount;

                return $value;
            }
        };
        $closure = Sudo::getMethodClosure($object, 'increment');
        $reflection = new \ReflectionFunction($closure);

        $this->assertTrue($reflection->returnsReference());
        $returnType = $reflection->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('int', $returnType->getName());
        $this->assertSame('value', $reflection->getParameters()[0]->getName());
        $this->assertTrue($reflection->getParameters()[0]->isPassedByReference());

        $value = 2;
        $result = &$closure($value);
        $this->assertSame(3, $value);
        $result = 4;
        $this->assertSame(4, $value);
    }

    public function testStaticMethodClosurePreservesCalledClass()
    {
        $object = new class() extends SudoClosureParent {
        };
        $class = \get_class($object);

        foreach (['publicClassName', 'privateClassName'] as $method) {
            $this->assertSame($class, Sudo::getStaticMethodClosure($class, $method)());
            $this->assertSame($class, Sudo::getStaticMethodClosure($object, $method)());
            $this->assertSame($class, Sudo::getMethodClosure($object, $method)());
        }
    }

    public function testMagicMethodClosure()
    {
        $object = new class() {
            public function __call($name, $args)
            {
                return [$name, $args];
            }

            public static function __callStatic($name, $args)
            {
                return [$name, $args];
            }
        };

        $this->assertSame(['missing', [1, 2]], Sudo::getMethodClosure($object, 'missing')(1, 2));
        $this->assertSame(['missing', [1, 2]], Sudo::getStaticMethodClosure($object, 'missing')(1, 2));
    }

    public function testStaticClosureRejectsInstanceMethod()
    {
        $this->expectException(\TypeError::class);

        Sudo::getStaticMethodClosure(new ClassWithSecrets(), 'privateMethod');
    }

    public function testStaticProperty()
    {
        $obj = new ClassWithSecrets();

        // Unfortunately, since this is global mutable state, we can't assert the initial value.
        // Running tests out of order will blow things up :(

        $this->assertSame('not so private now', Sudo::assignStaticProperty($obj, 'privateStaticProp', 'not so private now'));
        $this->assertSame('not so private now', Sudo::fetchStaticProperty($obj, 'privateStaticProp'));

        $this->assertSame('wheee', Sudo::assignStaticProperty($obj, 'privateStaticProp', 'wheee'));
        $this->assertSame('wheee', Sudo::fetchStaticProperty($obj, 'privateStaticProp'));
    }

    public function testCallStatic()
    {
        $obj = new ClassWithSecrets();
        $this->assertSame('private and static and method', Sudo::callStatic($obj, 'privateStaticMethod'));
        $this->assertSame('private and static and method with 1', Sudo::callStatic($obj, 'privateStaticMethod', 1));
        $this->assertSame(
            'private and static and method with ["foo",2]',
            Sudo::callStatic($obj, 'privateStaticMethod', ['foo', 2]
            ));
    }

    public function testFetchClassConst()
    {
        $obj = new ClassWithSecrets();
        $this->assertSame('private and const', Sudo::fetchClassConst($obj, 'PRIVATE_CONST'));
        $this->assertSame(ClassWithSecrets::class, Sudo::fetchClassConst($obj, 'class'));

        $obj2 = new \ArrayObject();
        $this->assertSame(\ArrayObject::class, Sudo::fetchClassConst($obj2, 'class'));
    }

    public function testParentProperties()
    {
        $obj = new ClassWithSecretiveParent();
        $this->assertSame('private and prop', Sudo::fetchProperty($obj, 'privateProp'));
        $this->assertSame('not so private now', Sudo::assignProperty($obj, 'privateProp', 'not so private now'));
        $this->assertSame('not so private now', Sudo::fetchProperty($obj, 'privateProp'));
    }

    public function testParentMethods()
    {
        $obj = new ClassWithSecretiveParent();
        $this->assertSame('private and method', Sudo::callMethod($obj, 'privateMethod'));
        $this->assertSame('private and method with 1', Sudo::callMethod($obj, 'privateMethod', 1));
        $this->assertSame(
            'private and method with ["foo",2]',
            Sudo::callMethod($obj, 'privateMethod', ['foo', 2]
            ));
    }

    public function testParentStaticProps()
    {
        $obj = new ClassWithSecretiveParent();

        // Unfortunately, since this is global mutable state, we can't assert the initial value.
        // Running tests out of order will blow things up :(

        $this->assertSame('not so private now', Sudo::assignStaticProperty($obj, 'privateStaticProp', 'not so private now'));
        $this->assertSame('not so private now', Sudo::fetchStaticProperty($obj, 'privateStaticProp'));

        $this->assertSame('wheee', Sudo::assignStaticProperty($obj, 'privateStaticProp', 'wheee'));
        $this->assertSame('wheee', Sudo::fetchStaticProperty($obj, 'privateStaticProp'));
    }

    public function testParentStaticMethods()
    {
        $obj = new ClassWithSecretiveParent();
        $this->assertSame('private and static and method', Sudo::callStatic($obj, 'privateStaticMethod'));
        $this->assertSame('private and static and method with 1', Sudo::callStatic($obj, 'privateStaticMethod', 1));
        $this->assertSame(
            'private and static and method with ["foo",2]',
            Sudo::callStatic($obj, 'privateStaticMethod', ['foo', 2]
            ));
    }

    public function testParentConsts()
    {
        $obj = new ClassWithSecretiveParent();
        $this->assertSame('private and const', Sudo::fetchClassConst($obj, 'PRIVATE_CONST'));
    }

    public function testNewInstance()
    {
        $obj = Sudo::newInstance(ClassWithSecretConstructor::class);
        $this->assertSame('private prop', Sudo::fetchProperty($obj, 'privateProp'));
    }

    public function testParentNewInstance()
    {
        $obj = Sudo::newInstance(ClassWithSecretParentConstructor::class, ['foo', 2]);
        $this->assertSame('private prop ["foo",2]', Sudo::fetchProperty($obj, 'privateProp'));
    }
}

class SudoClosureParent
{
    public static function publicClassName(): string
    {
        return static::class;
    }

    private static function privateClassName(): string
    {
        return static::class;
    }
}
