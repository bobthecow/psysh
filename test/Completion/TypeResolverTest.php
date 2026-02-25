<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion;

use Psy\Completion\TypeResolver;
use Psy\Context;
use Psy\Test\Fixtures\Completion\Address;
use Psy\Test\Fixtures\Completion\City;
use Psy\Test\Fixtures\Completion\Collection;
use Psy\Test\Fixtures\Completion\DnfTypeRepository;
use Psy\Test\Fixtures\Completion\IntersectionTypeRepository;
use Psy\Test\Fixtures\Completion\Repository;
use Psy\Test\Fixtures\Completion\UnionTypeRepository;
use Psy\Test\Fixtures\Completion\User;
use Psy\Test\TestCase;

// These are in a separate file because older PHP versions cannot parse the syntax
if (\PHP_VERSION_ID >= 80200) {
    require_once __DIR__.'/../Fixtures/Completion/TypeResolverPhp82Fixtures.php';
}

class TypeResolverTest extends TestCase
{
    private Context $context;
    private TypeResolver $resolver;

    public function setUp(): void
    {
        $this->context = new Context();
        $this->resolver = new TypeResolver($this->context);
    }

    /**
     * @dataProvider variableTypeProvider
     */
    public function testVariableTypeResolution(string $varName, $value, array $expectedTypes)
    {
        $this->context->setAll([$varName => $value]);

        $types = $this->resolver->resolveTypes('$'.$varName);

        $this->assertEquals($expectedTypes, $types);
    }

    public function variableTypeProvider(): array
    {
        return [
            ['str', 'hello', []],
            ['int', 42, []],
            ['float', 3.14, []],
            ['bool', true, []],
            ['null', null, []],

            ['arr', [1, 2, 3], []],
            ['empty', [], []],
            ['assoc', ['a' => 1], []],

            ['date', new \DateTime(), ['DateTime']],
            ['zone', new \DateTimeZone('UTC'), ['DateTimeZone']],
            ['obj', new \stdClass(), ['stdClass']],
        ];
    }

    public function testNonExistentVariable()
    {
        $types = $this->resolver->resolveTypes('$nonexistent');

        $this->assertEmpty($types);
    }

    public function testEmptyExpression()
    {
        $types = $this->resolver->resolveTypes('');

        $this->assertEmpty($types);
    }

    /**
     * @dataProvider classNameProvider
     */
    public function testClassNameResolution(string $className, array $expectedTypes)
    {
        $types = $this->resolver->resolveTypes($className);

        $this->assertEquals($expectedTypes, $types);
    }

    public function classNameProvider(): array
    {
        return [
            ['DateTime', ['DateTime']],
            ['DateTimeZone', ['DateTimeZone']],
            ['stdClass', ['stdClass']],
            ['Exception', ['Exception']],

            ['\\DateTime', ['\\DateTime']],
            ['\\DateTimeZone', ['\\DateTimeZone']],

            // Non-existent classes (returned as-is)
            ['NonExistent', ['NonExistent']],
            ['Foo\\Bar', ['Foo\\Bar']],
        ];
    }

    public function testSelfKeyword()
    {
        $types = $this->resolver->resolveTypes('self');
        $this->assertEmpty($types);

        $this->context->setBoundClass('DateTime');
        $types = $this->resolver->resolveTypes('self');
        $this->assertEquals(['DateTime'], $types);

        $this->context->setBoundClass(null);
        $this->context->setBoundObject(new \DateTime());
        $types = $this->resolver->resolveTypes('self');
        $this->assertEquals(['DateTime'], $types);
    }

    public function testStaticKeyword()
    {
        $types = $this->resolver->resolveTypes('static');
        $this->assertEmpty($types);

        $this->context->setBoundClass('DateTime');
        $types = $this->resolver->resolveTypes('static');
        $this->assertEquals(['DateTime'], $types);

        $this->context->setBoundClass(null);
        $this->context->setBoundObject(new \DateTime());
        $types = $this->resolver->resolveTypes('static');
        $this->assertEquals(['DateTime'], $types);
    }

    public function testParentKeyword()
    {
        $types = $this->resolver->resolveTypes('parent');
        $this->assertEmpty($types);

        $this->context->setBoundClass('Exception');
        $types = $this->resolver->resolveTypes('parent');
        $this->assertEmpty($types); // Exception has no parent

        $this->context->setBoundClass('RuntimeException');
        $types = $this->resolver->resolveTypes('parent');
        $this->assertEquals(['Exception'], $types);
    }

    /**
     * @dataProvider magicVariablesProvider
     */
    public function testMagicVariables(string $varName, callable $setup, array $expectedTypes)
    {
        $setup($this->context);

        $types = $this->resolver->resolveTypes('$'.$varName);

        $this->assertEquals($expectedTypes, $types);
    }

    public function magicVariablesProvider(): array
    {
        return [
            [
                '_',
                function ($ctx) {
                    $ctx->setReturnValue(new \DateTime());
                },
                ['DateTime'],
            ],
            [
                '_',
                function ($ctx) {
                    $ctx->setReturnValue('string');
                },
                [],
            ],
            [
                '_e',
                function ($ctx) {
                    $ctx->setLastException(new \Exception());
                },
                ['Exception'],
            ],
            [
                '_e',
                function ($ctx) {
                    $ctx->setLastException(new \RuntimeException());
                },
                ['RuntimeException'],
            ],
            [
                'this',
                function ($ctx) {
                    $ctx->setBoundObject(new \DateTime());
                },
                ['DateTime'],
            ],
        ];
    }

    public function testVariableWithDifferentCasing()
    {
        // PHP variables are case-sensitive
        $this->context->setAll([
            'foo' => 'lowercase',
            'Foo' => 'uppercase',
            'FOO' => 'allcaps',
        ]);

        $this->assertEquals([], $this->resolver->resolveTypes('$foo'));
        $this->assertEquals([], $this->resolver->resolveTypes('$Foo'));
        $this->assertEquals([], $this->resolver->resolveTypes('$FOO'));
    }

    public function testComplexVariableNames()
    {
        // Note: '_' is a special magic variable, so use a different name
        $this->context->setAll([
            '_foo'    => 'leading underscore',
            '__'      => 'double underscore',
            'foo_bar' => 'with underscore',
            'foo123'  => 'with numbers',
        ]);

        // Set the return value to test $_
        $this->context->setReturnValue('test value');
        $this->assertEquals([], $this->resolver->resolveTypes('$_'));

        $this->assertEquals([], $this->resolver->resolveTypes('$_foo'));
        $this->assertEquals([], $this->resolver->resolveTypes('$__'));
        $this->assertEquals([], $this->resolver->resolveTypes('$foo_bar'));
        $this->assertEquals([], $this->resolver->resolveTypes('$foo123'));
    }

    /**
     * @dataProvider variableValueProvider
     */
    public function testVariableValueResolution(string $varName, $value)
    {
        $this->context->setAll([$varName => $value]);

        $resolvedValue = $this->resolver->resolveValue('$'.$varName);

        $this->assertSame($value, $resolvedValue);
    }

    public function variableValueProvider(): array
    {
        $obj = new \DateTime('2025-01-01');

        return [
            // Scalar types
            ['str', 'hello'],
            ['int', 42],
            ['float', 3.14],
            ['bool', true],
            ['null', null],

            // Arrays
            ['arr', [1, 2, 3]],
            ['empty', []],
            ['assoc', ['a' => 1]],

            // Objects
            ['date', $obj],
            ['zone', new \DateTimeZone('UTC')],
        ];
    }

    public function testResolveValueNonExistentVariable()
    {
        $value = $this->resolver->resolveValue('$nonexistent');

        $this->assertNull($value);
    }

    public function testResolveValueEmptyExpression()
    {
        $value = $this->resolver->resolveValue('');

        $this->assertNull($value);
    }

    public function testResolveValueNonVariableExpression()
    {
        $this->assertNull($this->resolver->resolveValue('DateTime'));
        $this->assertNull($this->resolver->resolveValue('self'));
        $this->assertNull($this->resolver->resolveValue('1 + 2'));
    }

    /**
     * @dataProvider magicVariableValuesProvider
     */
    public function testMagicVariableValues(string $varName, callable $setup, $expectedValue)
    {
        $setup($this->context);

        $value = $this->resolver->resolveValue('$'.$varName);

        if (\is_object($expectedValue)) {
            $this->assertSame($expectedValue, $value);
        } else {
            $this->assertEquals($expectedValue, $value);
        }
    }

    public function magicVariableValuesProvider(): array
    {
        $date = new \DateTime('2025-01-01');
        $exception = new \RuntimeException('test');

        return [
            // $_ (last return value)
            [
                '_',
                function ($ctx) use ($date) {
                    $ctx->setReturnValue($date);
                },
                $date,
            ],
            [
                '_',
                function ($ctx) {
                    $ctx->setReturnValue('string value');
                },
                'string value',
            ],

            // $_e (last exception)
            [
                '_e',
                function ($ctx) use ($exception) {
                    $ctx->setLastException($exception);
                },
                $exception,
            ],

            // $this (bound object)
            [
                'this',
                function ($ctx) use ($date) {
                    $ctx->setBoundObject($date);
                },
                $date,
            ],
        ];
    }

    /**
     * @dataProvider methodChainingProvider
     */
    public function testMethodChaining(callable $setup, string $expression, array $expectedTypes)
    {
        $setup($this->context);

        $types = $this->resolver->resolveTypes($expression);

        $this->assertEquals($expectedTypes, $types);
    }

    public function methodChainingProvider(): array
    {
        return [
            // Simple chains
            'simple method chain' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->getAddress()',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],
            'two-level method chain' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->getAddress()->getCity()',
                ['Psy\Test\Fixtures\Completion\City'],
            ],
            'three-level method chain' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->getAddress()->getCity()->getCountry()',
                ['Psy\Test\Fixtures\Completion\Country'],
            ],

            // Property chains
            'property access' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->address',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],
            'property to property' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->address->city',
                ['Psy\Test\Fixtures\Completion\City'],
            ],

            // Mixed method and property
            'method then property' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->getAddress()->city',
                ['Psy\Test\Fixtures\Completion\City'],
            ],
            'property then method' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->address->getCity()',
                ['Psy\Test\Fixtures\Completion\City'],
            ],

            // Docblock return types
            'docblock return type' => [
                fn ($ctx) => $ctx->setAll(['city' => new City()]),
                '$city->getCountry()',
                ['Psy\Test\Fixtures\Completion\Country'],
            ],

            // Scalar returns
            'scalar return (string)' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->getName()',
                [],
            ],
            'scalar return in chain' => [
                fn ($ctx) => $ctx->setAll(['address' => new Address()]),
                '$address->getStreet()',
                [],
            ],

            // Error cases
            'non-existent method' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->nonExistent()',
                [],
            ],
            'non-existent method in chain' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->nonExistent()->getCity()',
                [],
            ],
            'non-existent variable' => [
                fn ($ctx) => null,
                '$nonexistent->getAddress()',
                [],
            ],

            // Special cases
            '$this keyword' => [
                fn ($ctx) => $ctx->setBoundObject(new User()),
                '$this->getAddress()',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],
        ];
    }

    public function testChainWithBuiltInClass()
    {
        $user = new User();
        $this->context->setAll(['user' => $user]);

        $types = $this->resolver->resolveTypes('$user->getAddress()');
        $this->assertEquals(['Psy\Test\Fixtures\Completion\Address'], $types);

        // Built-in classes may lack type info in reflection
        $this->context->setAll(['date' => new \DateTime('2025-01-01')]);
        $types = $this->resolver->resolveTypes('$date->getTimezone()');
        $this->assertTrue($types === [] || $types === ['DateTimeZone']);
    }

    public function testStaticFactoryCallResolvesToObjectType()
    {
        $types = $this->resolver->resolveTypes('DateTime::createFromFormat("Y-m-d", "2025-01-01")');

        $this->assertTrue($types === [] || $types === ['DateTime']);
    }

    public function testStaticFactoryChainWithIncompleteMemberReturnsReceiverType()
    {
        $types = $this->resolver->resolveTypes('DateTime::createFromFormat("Y-m-d", "2025-01-01")->for');

        $this->assertTrue($types === [] || $types === ['DateTime']);
    }

    /**
     * @requires PHP >= 8.0
     */
    public function testNullsafeChainResolution()
    {
        $this->context->setAll(['user' => new User()]);

        $types = $this->resolver->resolveTypes('$user?->getAddress()?->getCity()');

        $this->assertEquals(['Psy\Test\Fixtures\Completion\City'], $types);
    }

    public function testChainDoesNotEvaluateCode()
    {
        $user = new User();
        $this->context->setAll(['user' => $user]);

        // Should only look at the return type, not actually execute
        $types = $this->resolver->resolveTypes('$user->getAddress()');
        $this->assertEquals(['Psy\Test\Fixtures\Completion\Address'], $types);

        $this->assertInstanceOf(User::class, $user);
    }

    /**
     * @dataProvider methodArgumentsProvider
     */
    public function testMethodCallsWithArguments(callable $setup, string $expression, array $expectedTypes)
    {
        $setup($this->context);

        $types = $this->resolver->resolveTypes($expression);

        $this->assertEquals($expectedTypes, $types);
    }

    public function methodArgumentsProvider(): array
    {
        $cases = [
            // Simple arguments
            'single integer argument' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->find(123)',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'multiple arguments' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->findOneBy(["id" => 1], ["name" => "ASC"])',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'optional arguments omitted' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->findOneBy(["id" => 1])',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'all optional arguments provided' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->query("SELECT *", [], true)',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'variable as argument' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository(), 'id' => 42]),
                '$repo->find($id)',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Array arguments (AST advantage over regex)
            'empty array' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->findOneBy([])',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'simple array with content' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->findOneBy(["name" => "John"])',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'nested array argument' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->findOneBy(["age" => [">=" => 18], "status" => "active"])',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Closure arguments
            'traditional closure' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->findWhere(function($user) { return $user->active; })',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Complex expressions in arguments
            'string with arrow operator' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->query("SELECT a->b FROM json")',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'nested method call' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->find(getId())',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'ternary expression' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository(), 'active' => true]),
                '$repo->find($active ? 1 : 2)',
                ['Psy\Test\Fixtures\Completion\User'],
            ],
            'null coalesce operator' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository(), 'id' => null]),
                '$repo->find($id ?? 1)',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Chained method calls with arguments
            'chained with arguments' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->find(123)->getAddress()',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],
            'two-level chain with arguments' => [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->find(123)->getAddress()->getCity()',
                ['Psy\Test\Fixtures\Completion\City'],
            ],

            // Fluent interfaces
            'complex chain with mixed arguments' => [
                fn ($ctx) => $ctx->setAll(['collection' => new Collection()]),
                '$collection->slice(0, 10)->first()->getAddress()->getCity()',
                ['Psy\Test\Fixtures\Completion\City'],
            ],
        ];

        // Arrow function tests require php-parser 4.2+ for fn() syntax
        if (\file_exists(__DIR__.'/../../vendor/nikic/php-parser/lib/PhpParser/Node/Expr/ArrowFunction.php')) {
            $cases['arrow function'] = [
                fn ($ctx) => $ctx->setAll(['repo' => new Repository()]),
                '$repo->findWhere(fn($u) => $u->id > 10)',
                ['Psy\Test\Fixtures\Completion\User'],
            ];
            $cases['fluent method with closure'] = [
                fn ($ctx) => $ctx->setAll(['collection' => new Collection()]),
                '$collection->filter(fn($x) => $x > 5)',
                ['Psy\Test\Fixtures\Completion\Collection'],
            ];
            $cases['chained fluent methods'] = [
                fn ($ctx) => $ctx->setAll(['collection' => new Collection()]),
                '$collection->filter(fn($x) => $x > 5)->map(fn($x) => $x * 2)',
                ['Psy\Test\Fixtures\Completion\Collection'],
            ];
            $cases['fluent chain ending with type change'] = [
                fn ($ctx) => $ctx->setAll(['collection' => new Collection()]),
                '$collection->filter(fn($x) => true)->first()',
                ['Psy\Test\Fixtures\Completion\User'],
            ];
        }

        return $cases;
    }

    /**
     * @dataProvider unionTypeProvider
     *
     * @requires PHP >= 8.2
     */
    public function testUnionTypes(callable $setup, string $expression, array $expectedTypes)
    {
        $setup($this->context);

        $types = $this->resolver->resolveTypes($expression);

        // Sort both arrays for comparison since order doesn't matter
        \sort($expectedTypes);
        \sort($types);

        $this->assertEquals($expectedTypes, $types);
    }

    public function unionTypeProvider(): array
    {
        if (\PHP_VERSION_ID < 80200) {
            return [['fn() => null', 'self', []]];
        }

        return [
            // Two-type union
            'union of User and Address' => [
                fn ($ctx) => $ctx->setAll(['repo' => new UnionTypeRepository()]),
                '$repo->findEntity(1)',
                ['Psy\Test\Fixtures\Completion\Address', 'Psy\Test\Fixtures\Completion\User'],
            ],

            // Another two-type union
            'union of City and Country' => [
                fn ($ctx) => $ctx->setAll(['repo' => new UnionTypeRepository()]),
                '$repo->getLocation("city")',
                ['Psy\Test\Fixtures\Completion\City', 'Psy\Test\Fixtures\Completion\Country'],
            ],

            // Union with null (should only return the class type)
            'union with null' => [
                fn ($ctx) => $ctx->setAll(['repo' => new UnionTypeRepository()]),
                '$repo->findOptional(1)',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Three-way union
            'three-way union' => [
                fn ($ctx) => $ctx->setAll(['repo' => new UnionTypeRepository()]),
                '$repo->getAny()',
                ['Psy\Test\Fixtures\Completion\Address', 'Psy\Test\Fixtures\Completion\City', 'Psy\Test\Fixtures\Completion\User'],
            ],

            // Union with scalar (should filter out 'false')
            'union with scalar' => [
                fn ($ctx) => $ctx->setAll(['repo' => new UnionTypeRepository()]),
                '$repo->getResult()',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Chained union, calling method on union type result
            'chained from union' => [
                fn ($ctx) => $ctx->setAll(['repo' => new UnionTypeRepository()]),
                '$repo->findEntity(1)->getAddress()',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],

            // Union followed by property that exists in both types
            'union with common property' => [
                fn ($ctx) => $ctx->setAll(['repo' => new UnionTypeRepository()]),
                '$repo->getLocation("city")->getCode()',
                [],
            ],
        ];
    }

    /**
     * @dataProvider intersectionTypeProvider
     *
     * @requires PHP >= 8.2
     */
    public function testIntersectionTypes(callable $setup, string $expression, array $expectedTypes)
    {
        $setup($this->context);

        $types = $this->resolver->resolveTypes($expression);

        // Sort both arrays for comparison since order doesn't matter
        \sort($expectedTypes);
        \sort($types);

        $this->assertEquals($expectedTypes, $types);
    }

    public function intersectionTypeProvider(): array
    {
        if (\PHP_VERSION_ID < 80200) {
            return [['fn() => null', 'self', []]];
        }

        return [
            // Two-type intersection
            'intersection of Loggable and Timestamped' => [
                fn ($ctx) => $ctx->setAll(['repo' => new IntersectionTypeRepository()]),
                '$repo->getLoggable()',
                ['Psy\Test\Fixtures\Completion\Loggable', 'Psy\Test\Fixtures\Completion\Timestamped'],
            ],

            // Three-way intersection
            'three-way intersection' => [
                fn ($ctx) => $ctx->setAll(['repo' => new IntersectionTypeRepository()]),
                '$repo->getEntity()',
                ['Countable', 'Psy\Test\Fixtures\Completion\Loggable', 'Psy\Test\Fixtures\Completion\Timestamped'],
            ],

            // Chained from intersection result
            'chained from intersection' => [
                fn ($ctx) => $ctx->setAll(['repo' => new IntersectionTypeRepository()]),
                '$repo->getLoggable()->log()',
                [],
            ],
        ];
    }

    /**
     * @dataProvider dnfTypeProvider
     *
     * @requires PHP >= 8.2
     */
    public function testDnfTypes(callable $setup, string $expression, array $expectedTypes)
    {
        $setup($this->context);

        $types = $this->resolver->resolveTypes($expression);

        // Sort both arrays for comparison since order doesn't matter
        \sort($expectedTypes);
        \sort($types);

        $this->assertEquals($expectedTypes, $types);
    }

    public function dnfTypeProvider(): array
    {
        if (\PHP_VERSION_ID < 80200) {
            return [['fn() => null', 'self', []]];
        }

        return [
            // DNF: (A&B)|C
            'simple DNF type' => [
                fn ($ctx) => $ctx->setAll(['repo' => new DnfTypeRepository()]),
                '$repo->getComplex()',
                ['Psy\Test\Fixtures\Completion\Loggable', 'Psy\Test\Fixtures\Completion\Timestamped', 'Psy\Test\Fixtures\Completion\User'],
            ],

            // DNF: (A&B)|(C&D)
            'complex DNF type' => [
                fn ($ctx) => $ctx->setAll(['repo' => new DnfTypeRepository()]),
                '$repo->getVeryComplex()',
                ['Countable', 'Psy\Test\Fixtures\Completion\Loggable', 'Psy\Test\Fixtures\Completion\Timestamped', 'Psy\Test\Fixtures\Completion\User'],
            ],
        ];
    }

    /**
     * @dataProvider incompleteInputProvider
     */
    public function testIncompleteInput(callable $setup, string $expression, array $expectedTypes)
    {
        $setup($this->context);

        $types = $this->resolver->resolveTypes($expression);

        // Sort both arrays for comparison since order doesn't matter
        \sort($expectedTypes);
        \sort($types);

        $this->assertEquals($expectedTypes, $types);
    }

    public function incompleteInputProvider(): array
    {
        return [
            // Simple variable at end of incomplete statement
            'variable after if statement' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                'if (true) { $user',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Variable with trailing pipe (what does this even mean?)
            'variable with trailing pipe' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user|',
                [],
            ],

            // Incomplete method call (no closing paren)
            'incomplete method call' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->getAddress(',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],

            // Incomplete chain
            'incomplete chain with arrow' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->getAddress()->',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],

            // Variable in incomplete array
            'variable in incomplete array' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '[$user',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Variable after incomplete operator
            'variable after incomplete addition' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$x + $user',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Method chain with incomplete last segment
            'chain with incomplete method name' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->getAddress()->getC',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],

            // Variable after semicolon (multiline)
            'variable after semicolon' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$x = 1; $user',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Incomplete property access
            'incomplete property access' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->address->',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],
            'incomplete property access with prefix' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user->address->getC',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],

            // Variable with incomplete string after it (the "type" here would be string)
            'variable before incomplete string' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '$user . "',
                [],
            ],

            // Deeply nested incomplete input
            'nested in function call' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                'echo foo($user',
                ['Psy\Test\Fixtures\Completion\User'],
            ],

            // Chain after conditional
            'chain after ternary' => [
                fn ($ctx) => $ctx->setAll(['user' => new User()]),
                '($x ? $user->getAddress() : null)',
                ['Psy\Test\Fixtures\Completion\Address'],
            ],
        ];
    }
}
