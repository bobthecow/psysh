<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

use Psy\Test\Fixtures\Util\MagicChild;
use Psy\Test\Fixtures\Util\MagicClass;
use Psy\Test\Fixtures\Util\NoMagicClass;
use Psy\Util\Docblock;

class DocblockTest extends \Psy\Test\TestCase
{
    protected function tearDown(): void
    {
        Docblock::clearMagicCache();
    }

    /**
     * @dataProvider comments
     */
    public function testDocblockParsing($comment, $body, $tags)
    {
        $reflector = $this
            ->getMockBuilder(\ReflectionClass::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflector->expects($this->once())
            ->method('getDocComment')
            ->willReturn($comment);

        $docblock = new Docblock($reflector);

        $this->assertSame($body, $docblock->desc);

        foreach ($tags as $tag => $value) {
            $this->assertTrue($docblock->hasTag($tag));
            $this->assertEquals($value, $docblock->tag($tag));
        }
    }

    public function comments()
    {
        return [
            ['', '', []],
            [
                '/**
                 * This is a docblock
                 *
                 * @throws \Exception with a description
                 */',
                'This is a docblock',
                [
                    'throws' => [['type' => '\Exception', 'desc' => 'with a description']],
                ],
            ],
            [
                '/**
                 * This is a slightly longer docblock
                 *
                 * @param int         $foo Is a Foo
                 * @param string      $bar With some sort of description
                 * @param \ClassName $baz is cool too
                 *
                 * @return int At least it isn\'t a string
                 */',
                'This is a slightly longer docblock',
                [
                    'param' => [
                        ['type' => 'int', 'desc' => 'Is a Foo', 'var' => '$foo'],
                        ['type' => 'string', 'desc' => 'With some sort of description', 'var' => '$bar'],
                        ['type' => '\ClassName', 'desc' => 'is cool too', 'var' => '$baz'],
                    ],
                    'return' => [
                        ['type' => 'int', 'desc' => 'At least it isn\'t a string'],
                    ],
                ],
            ],
            [
                '/**
                 * This is a docblock!
                 *
                 * It spans lines, too!
                 *
                 * @tagname plus a description
                 *
                 * @return
                 */',
                "This is a docblock!\n\nIt spans lines, too!",
                [
                    'tagname' => ['plus a description'],
                ],
            ],
            [
                '/**
                 * This is a single-line docblock.
                 */',
                'This is a single-line docblock.',
                [],
            ],
            [
                '/** This is a single-line docblock. */',
                'This is a single-line docblock.',
                [],
            ],
        ];
    }

    /**
     * @dataProvider methodTags
     */
    public function testGetMethods($comment, $expected)
    {
        $reflector = $this
            ->getMockBuilder(\ReflectionClass::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflector->expects($this->once())
            ->method('getDocComment')
            ->willReturn($comment);

        $reflector->method('getName')
            ->willReturn('TestClass');

        $docblock = new Docblock($reflector);
        $methods = $docblock->getMethods();

        $this->assertCount(\count($expected), $methods);

        foreach ($expected as $i => $expectedMethod) {
            $this->assertInstanceOf(\Psy\Reflection\ReflectionMagicMethod::class, $methods[$i]);
            $this->assertSame($expectedMethod['name'], $methods[$i]->getName());
            $this->assertSame($expectedMethod['static'], $methods[$i]->isStatic());
            $this->assertSame($expectedMethod['returnType'], $methods[$i]->getDocblockReturnType());
            $this->assertSame($expectedMethod['returnsReference'] ?? false, $methods[$i]->returnsReference());
            $this->assertSame($expectedMethod['parameters'], $methods[$i]->getParameterString());
            $this->assertSame($expectedMethod['description'], $methods[$i]->getDescription());
        }
    }

    public function methodTags()
    {
        return [
            'simple method' => [
                '/**
                  * @method string getName()
                  */',
                [
                    [
                        'name'        => 'getName',
                        'static'      => false,
                        'returnType'  => 'string',
                        'parameters'  => '',
                        'description' => null,
                    ],
                ],
            ],
            'static method' => [
                '/**
                  * @method static User find(int $id)
                  */',
                [
                    [
                        'name'        => 'find',
                        'static'      => true,
                        'returnType'  => 'User',
                        'parameters'  => 'int $id',
                        'description' => null,
                    ],
                ],
            ],
            'method with description' => [
                '/**
                  * @method void save() Persist to database
                  */',
                [
                    [
                        'name'        => 'save',
                        'static'      => false,
                        'returnType'  => 'void',
                        'parameters'  => '',
                        'description' => 'Persist to database',
                    ],
                ],
            ],
            'method with union return type' => [
                '/**
                  * @method Builder|User where(string $col, $val)
                  */',
                [
                    [
                        'name'        => 'where',
                        'static'      => false,
                        'returnType'  => 'Builder|User',
                        'parameters'  => 'string $col, $val',
                        'description' => null,
                    ],
                ],
            ],
            'method with nullable return type' => [
                '/**
                  * @method ?string getOptional()
                  */',
                [
                    [
                        'name'        => 'getOptional',
                        'static'      => false,
                        'returnType'  => '?string',
                        'parameters'  => '',
                        'description' => null,
                    ],
                ],
            ],
            'method with array return type' => [
                '/**
                  * @method string[] getNames()
                  */',
                [
                    [
                        'name'        => 'getNames',
                        'static'      => false,
                        'returnType'  => 'string[]',
                        'parameters'  => '',
                        'description' => null,
                    ],
                ],
            ],
            'method without return type' => [
                '/**
                  * @method doSomething()
                  */',
                [
                    [
                        'name'        => 'doSomething',
                        'static'      => false,
                        'returnType'  => null,
                        'parameters'  => '',
                        'description' => null,
                    ],
                ],
            ],
            'static method with static return' => [
                '/**
                  * @method static static create(array $data)
                  */',
                [
                    [
                        'name'        => 'create',
                        'static'      => true,
                        'returnType'  => 'static',
                        'parameters'  => 'array $data',
                        'description' => null,
                    ],
                ],
            ],
            'multiple methods' => [
                '/**
                  * @method string getName()
                  * @method void setName(string $name)
                  * @method static self create()
                  */',
                [
                    [
                        'name'        => 'getName',
                        'static'      => false,
                        'returnType'  => 'string',
                        'parameters'  => '',
                        'description' => null,
                    ],
                    [
                        'name'        => 'setName',
                        'static'      => false,
                        'returnType'  => 'void',
                        'parameters'  => 'string $name',
                        'description' => null,
                    ],
                    [
                        'name'        => 'create',
                        'static'      => true,
                        'returnType'  => 'self',
                        'parameters'  => '',
                        'description' => null,
                    ],
                ],
            ],
            'method returns by reference' => [
                '/**
                  * @method mixed &getRef()
                  */',
                [
                    [
                        'name'             => 'getRef',
                        'static'           => false,
                        'returnType'       => 'mixed',
                        'returnsReference' => true,
                        'parameters'       => '',
                        'description'      => null,
                    ],
                ],
            ],
            'static method returns by reference' => [
                '/**
                  * @method static array &getStaticRef()
                  */',
                [
                    [
                        'name'             => 'getStaticRef',
                        'static'           => true,
                        'returnType'       => 'array',
                        'returnsReference' => true,
                        'parameters'       => '',
                        'description'      => null,
                    ],
                ],
            ],
            'no method tags' => [
                '/**
                  * Just a regular docblock
                  */',
                [],
            ],
            'empty docblock' => [
                '',
                [],
            ],
        ];
    }

    /**
     * @dataProvider propertyTags
     */
    public function testGetProperties($comment, $expected)
    {
        $reflector = $this
            ->getMockBuilder(\ReflectionClass::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflector->expects($this->once())
            ->method('getDocComment')
            ->willReturn($comment);

        $reflector->method('getName')
            ->willReturn('TestClass');

        $docblock = new Docblock($reflector);
        $properties = $docblock->getProperties();

        $this->assertCount(\count($expected), $properties);

        foreach ($expected as $i => $expectedProperty) {
            $this->assertInstanceOf(\Psy\Reflection\ReflectionMagicProperty::class, $properties[$i]);
            $this->assertSame($expectedProperty['name'], $properties[$i]->getName());
            $this->assertSame($expectedProperty['type'], $properties[$i]->getDocblockType());
            $this->assertSame($expectedProperty['readOnly'], $properties[$i]->isReadOnly());
            $this->assertSame($expectedProperty['writeOnly'], $properties[$i]->isWriteOnly());
            $this->assertSame($expectedProperty['description'], $properties[$i]->getDescription());
        }
    }

    public function propertyTags()
    {
        return [
            'simple property' => [
                '/**
                  * @property string $name
                  */',
                [
                    [
                        'name'        => 'name',
                        'type'        => 'string',
                        'readOnly'    => false,
                        'writeOnly'   => false,
                        'description' => null,
                    ],
                ],
            ],
            'read-only property' => [
                '/**
                  * @property-read int $id
                  */',
                [
                    [
                        'name'        => 'id',
                        'type'        => 'int',
                        'readOnly'    => true,
                        'writeOnly'   => false,
                        'description' => null,
                    ],
                ],
            ],
            'write-only property' => [
                '/**
                  * @property-write string $password
                  */',
                [
                    [
                        'name'        => 'password',
                        'type'        => 'string',
                        'readOnly'    => false,
                        'writeOnly'   => true,
                        'description' => null,
                    ],
                ],
            ],
            'property with description' => [
                '/**
                  * @property string $title The document title
                  */',
                [
                    [
                        'name'        => 'title',
                        'type'        => 'string',
                        'readOnly'    => false,
                        'writeOnly'   => false,
                        'description' => 'The document title',
                    ],
                ],
            ],
            'property with union type' => [
                '/**
                  * @property Collection|User[] $users
                  */',
                [
                    [
                        'name'        => 'users',
                        'type'        => 'Collection|User[]',
                        'readOnly'    => false,
                        'writeOnly'   => false,
                        'description' => null,
                    ],
                ],
            ],
            'property without $ prefix' => [
                '/**
                  * @property string name
                  */',
                [
                    [
                        'name'        => 'name',
                        'type'        => 'string',
                        'readOnly'    => false,
                        'writeOnly'   => false,
                        'description' => null,
                    ],
                ],
            ],
            'multiple properties' => [
                '/**
                  * @property string $name
                  * @property-read int $id
                  * @property-write string $password
                  */',
                [
                    [
                        'name'        => 'name',
                        'type'        => 'string',
                        'readOnly'    => false,
                        'writeOnly'   => false,
                        'description' => null,
                    ],
                    [
                        'name'        => 'id',
                        'type'        => 'int',
                        'readOnly'    => true,
                        'writeOnly'   => false,
                        'description' => null,
                    ],
                    [
                        'name'        => 'password',
                        'type'        => 'string',
                        'readOnly'    => false,
                        'writeOnly'   => true,
                        'description' => null,
                    ],
                ],
            ],
            'no property tags' => [
                '/**
                  * Just a regular docblock
                  */',
                [],
            ],
        ];
    }

    public function testGetMagicMethodsFromClass()
    {
        $class = new \ReflectionClass(MagicClass::class);
        $methods = Docblock::getMagicMethods($class);

        $names = \array_map(fn ($m) => $m->getName(), $methods);

        $this->assertContains('getName', $names);
        $this->assertContains('setName', $names);
        $this->assertContains('find', $names);

        // Check static method
        $findMethod = \array_filter($methods, fn ($m) => $m->getName() === 'find');
        $findMethod = \reset($findMethod);
        $this->assertTrue($findMethod->isStatic());
    }

    public function testGetMagicPropertiesFromClass()
    {
        $class = new \ReflectionClass(MagicClass::class);
        $properties = Docblock::getMagicProperties($class);

        $names = \array_map(fn ($p) => $p->getName(), $properties);

        $this->assertContains('title', $names);
        $this->assertContains('id', $names);
        $this->assertContains('password', $names);

        // Check read-only property
        $idProperty = \array_filter($properties, fn ($p) => $p->getName() === 'id');
        $idProperty = \reset($idProperty);
        $this->assertTrue($idProperty->isReadOnly());

        // Check write-only property
        $passwordProperty = \array_filter($properties, fn ($p) => $p->getName() === 'password');
        $passwordProperty = \reset($passwordProperty);
        $this->assertTrue($passwordProperty->isWriteOnly());
    }

    public function testGetMagicMethodsInheritance()
    {
        $class = new \ReflectionClass(MagicChild::class);
        $methods = Docblock::getMagicMethods($class);

        $names = \array_map(fn ($m) => $m->getName(), $methods);

        // Should have parent's methods
        $this->assertContains('getParentMethod', $names);

        // Should have child's methods
        $this->assertContains('getChildMethod', $names);
    }

    public function testGetMagicMethodsFromInterface()
    {
        $class = new \ReflectionClass(MagicChild::class);
        $methods = Docblock::getMagicMethods($class);

        $names = \array_map(fn ($m) => $m->getName(), $methods);

        // Should have interface's methods
        $this->assertContains('getInterfaceMethod', $names);
    }

    public function testGetMagicMethodsFromTrait()
    {
        $class = new \ReflectionClass(MagicChild::class);
        $methods = Docblock::getMagicMethods($class);

        $names = \array_map(fn ($m) => $m->getName(), $methods);

        // Should have trait's methods
        $this->assertContains('getTraitMethod', $names);
    }

    public function testGetMagicMethodsChildOverridesParent()
    {
        $class = new \ReflectionClass(MagicChild::class);
        $methods = Docblock::getMagicMethods($class);

        // Find the overridden method
        $overridden = \array_filter($methods, fn ($m) => $m->getName() === 'overriddenMethod');
        $overridden = \reset($overridden);

        // Should have child's return type, not parent's
        $this->assertEquals('string', $overridden->getDocblockReturnType());
    }

    public function testGetMagicMethodsCaching()
    {
        $class = new \ReflectionClass(MagicClass::class);

        // First call
        $methods1 = Docblock::getMagicMethods($class);

        // Second call should return cached result
        $methods2 = Docblock::getMagicMethods($class);

        $this->assertSame($methods1, $methods2);
    }

    public function testClearMagicCache()
    {
        $class = new \ReflectionClass(MagicClass::class);

        // Populate cache
        Docblock::getMagicMethods($class);
        Docblock::getMagicProperties($class);

        // Clear cache
        Docblock::clearMagicCache();

        // This should work without issues (cache was cleared)
        $methods = Docblock::getMagicMethods($class);
        $this->assertNotEmpty($methods);
    }

    public function testNoMagicMethods()
    {
        $class = new \ReflectionClass(NoMagicClass::class);
        $methods = Docblock::getMagicMethods($class);

        $this->assertEmpty($methods);
    }

    public function testNoMagicProperties()
    {
        $class = new \ReflectionClass(NoMagicClass::class);
        $properties = Docblock::getMagicProperties($class);

        $this->assertEmpty($properties);
    }
}
