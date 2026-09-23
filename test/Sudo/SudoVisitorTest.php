<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Sudo;

use PhpParser\NodeTraverser;
use Psy\Sudo\SudoVisitor;
use Psy\Test\ParserTestCase;

class SudoVisitorTest extends ParserTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        // @todo Pass visitor directly to once we drop support for PHP-Parser 4.x
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new SudoVisitor());
    }

    /**
     * @dataProvider propertyFetches
     */
    public function testPropertyFetch($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function propertyFetches()
    {
        return [
            ['$a->b', "\Psy\Sudo::fetchProperty(\$a, 'b');"],
            ['$a->$b', '\Psy\Sudo::fetchProperty($a, $b);'],
            ["\$a->{'b'}", "\Psy\Sudo::fetchProperty(\$a, 'b');"],
        ];
    }

    /**
     * @dataProvider propertyAssigns
     */
    public function testPropertyAssign($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function propertyAssigns()
    {
        return [
            ['$a->b = $c', "\Psy\Sudo::assignProperty(\$a, 'b', \$c);"],
            ['$a->$b = $c', '\Psy\Sudo::assignProperty($a, $b, $c);'],
            ["\$a->{'b'} = \$c", "\Psy\Sudo::assignProperty(\$a, 'b', \$c);"],
        ];
    }

    /**
     * @dataProvider methodCalls
     */
    public function testMethodCall($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function methodCalls()
    {
        return [
            ['$a->b()', "\Psy\Sudo::callMethod(\$a, 'b');"],
            ['$a->$b()', '\Psy\Sudo::callMethod($a, $b);'],
            ["\$a->b(\$c, 'd')", "\Psy\Sudo::callMethod(\$a, 'b', \$c, 'd');"],
            ["\$a->\$b(\$c, 'd')", "\Psy\Sudo::callMethod(\$a, \$b, \$c, 'd');"],
        ];
    }

    /**
     * @dataProvider firstClassCallables
     */
    public function testFirstClassCallable($from, $to)
    {
        if (!\method_exists(\PhpParser\Node\Expr\FuncCall::class, 'isFirstClassCallable')) {
            $this->markTestSkipped('First-class callables require PHP-Parser 4.13 or newer.');
        }

        $this->assertProcessesAs($from, $to);
    }

    public function firstClassCallables()
    {
        return [
            ['$a->b(...)', "(\\Psy\\Sudo::getMethodClosure(\$a, 'b'))(...);"],
            ['$a->$b(...)', '(\Psy\Sudo::getMethodClosure($a, $b))(...);'],
            ['A::b(...)', "(\\Psy\\Sudo::getStaticMethodClosure('A', 'b'))(...);"],
            ['$a::$b(...)', '(\Psy\Sudo::getStaticMethodClosure($a, $b))(...);'],
            ['new A(...)', 'new A(...);'],
        ];
    }

    /**
     * @dataProvider partialApplications
     */
    public function testPartialApplication($from, $to)
    {
        if (!\method_exists(\PhpParser\Node\Expr\FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('Partial function application requires PHP-Parser 5.9 or newer.');
        }

        $this->assertProcessesAs($from, $to);
    }

    public function partialApplications()
    {
        return [
            ['$a->b(?)', "(\\Psy\\Sudo::getMethodClosure(\$a, 'b'))(?);"],
            ['$a->$b(?, 2)', '(\Psy\Sudo::getMethodClosure($a, $b))(?, 2);'],
            ['$a->b(1, ...)', "(\\Psy\\Sudo::getMethodClosure(\$a, 'b'))(1, ...);"],
            ['$a->b(extra: ?)', "(\\Psy\\Sudo::getMethodClosure(\$a, 'b'))(extra: ?);"],
            ['A::b(?, ...)', "(\\Psy\\Sudo::getStaticMethodClosure('A', 'b'))(?, ...);"],
            ['$a::$b(?, 2)', '(\Psy\Sudo::getStaticMethodClosure($a, $b))(?, 2);'],
            ['$a->b($c->d(), ?)', "(\\Psy\\Sudo::getMethodClosure(\$a, 'b'))(\\Psy\\Sudo::callMethod(\$c, 'd'), ?);"],
            ['$a->b($c->d(?), ?)', "(\\Psy\\Sudo::getMethodClosure(\$a, 'b'))((\\Psy\\Sudo::getMethodClosure(\$c, 'd'))(?), ?);"],
            ['new A(?)', 'new A(?);'],
        ];
    }

    public function testFirstClassCallableDoesNotInvokeMethod()
    {
        if (\PHP_VERSION_ID < 80100 || !\method_exists(\PhpParser\Node\Expr\FuncCall::class, 'isFirstClassCallable')) {
            $this->markTestSkipped('First-class callables require PHP 8.1 and PHP-Parser 4.13 or newer.');
        }

        $object = new class() {
            public $calls = 0;

            private function increment(int &$value): int
            {
                ++$this->calls;

                return ++$value;
            }
        };
        $closure = $this->evaluate('return $object->increment(...);', $object);
        $this->assertSame(0, $object->calls);
        $value = 1;
        $this->assertSame(2, $closure($value));
        $this->assertSame(2, $value);
        $this->assertSame(1, $object->calls);
    }

    public function testPartialApplicationPreservesReferencesAndBoundArguments()
    {
        $this->requirePartialApplication();
        $object = new class() {
            public $calls = 0;

            private function &increment(int &$value, int $amount): int
            {
                ++$this->calls;
                $value += $amount;

                return $value;
            }
        };

        $closure = $this->evaluate('return $object->increment(?, 2);', $object);
        $this->assertSame(0, $object->calls);
        $value = 1;
        $result = &$closure($value);
        $this->assertSame(3, $value);
        $this->assertSame(1, $object->calls);
        $result = 5;
        $this->assertSame(5, $value);
    }

    public function testPartialApplicationPreservesNamedArguments()
    {
        $this->requirePartialApplication();
        $object = new class() {
            private function join(string $first, string $second): string
            {
                return $first.$second;
            }
        };

        $closure = $this->evaluate('return $object->join(second: ?, first: "hello ");', $object);
        $this->assertSame('hello world', $closure('world'));
        $this->assertSame('second', (new \ReflectionFunction($closure))->getParameters()[0]->getName());
    }

    public function testPartialApplicationBindsReferences()
    {
        $this->requirePartialApplication();
        $object = new class() {
            private function increment(int &$value, int $amount): void
            {
                $value += $amount;
            }
        };

        $this->assertSame(3, $this->evaluate('$value = 1; $closure = $object->increment($value, ?); $closure(2); return $value;', $object));
    }

    public function testPartialApplicationEvaluatesTargetAndBoundArgumentsOnce()
    {
        $this->requirePartialApplication();
        $object = new class() {
            public $calls = [];

            private function target(): self
            {
                $this->calls[] = 'target';

                return $this;
            }

            private function prefix(): string
            {
                $this->calls[] = 'prefix';

                return 'hello ';
            }

            private function join(string $first, string $second): string
            {
                $this->calls[] = 'join';

                return $first.$second;
            }
        };

        $closure = $this->evaluate('return $object->target()->join($object->prefix(), ?);', $object);
        $this->assertSame(['target', 'prefix'], $object->calls);
        $this->assertSame('hello world', $closure('world'));
        $this->assertSame('hello again', $closure('again'));
        $this->assertSame(['target', 'prefix', 'join', 'join'], $object->calls);
    }

    public function testStaticPartialApplicationPreservesRemainingParameters()
    {
        $this->requirePartialApplication();
        $object = new class() {
            private static function join(string $first, string $second, string $third = '!'): string
            {
                return $first.$second.$third;
            }
        };

        $closure = $this->evaluate('return $object::join("hello ", ...);', $object);
        $this->assertSame('hello world!', $closure('world'));
        $parameters = (new \ReflectionFunction($closure))->getParameters();
        $this->assertSame('second', $parameters[0]->getName());
        $this->assertSame('third', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->isOptional());
    }

    private function requirePartialApplication(): void
    {
        if (\PHP_VERSION_ID < 80600 || !\method_exists(\PhpParser\Node\Expr\FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('Partial function application requires PHP 8.6 and PHP-Parser 5.9 or newer.');
        }
    }

    private function evaluate(string $code, $object)
    {
        return eval($this->prettyPrint($this->traverse($this->parse($code))));
    }

    /**
     * @dataProvider staticPropertyFetches
     */
    public function testStaticPropertyFetch($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function staticPropertyFetches()
    {
        return [
            ['A::$b', "\Psy\Sudo::fetchStaticProperty('A', 'b');"],
            ['$a::$b', "\Psy\Sudo::fetchStaticProperty(\$a, 'b');"],
        ];
    }

    /**
     * @dataProvider staticPropertyAssigns
     */
    public function testStaticPropertyAssign($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function staticPropertyAssigns()
    {
        return [
            ['A::$b = $c', "\Psy\Sudo::assignStaticProperty('A', 'b', \$c);"],
            ['$a::$b = $c', "\Psy\Sudo::assignStaticProperty(\$a, 'b', \$c);"],
        ];
    }

    /**
     * @dataProvider staticCalls
     */
    public function testStaticCall($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function staticCalls()
    {
        return [
            ['A::b()', "\Psy\Sudo::callStatic('A', 'b');"],
            ['A::$b()', "\Psy\Sudo::callStatic('A', \$b);"],
            ["A::b(\$c, 'd')", "\Psy\Sudo::callStatic('A', 'b', \$c, 'd');"],
            ["A::\$b(\$c, 'd')", "\Psy\Sudo::callStatic('A', \$b, \$c, 'd');"],
        ];
    }

    /**
     * @dataProvider classConstFetches
     */
    public function testClassConstFetch($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function classConstFetches()
    {
        return [
            ['A::B', "\Psy\Sudo::fetchClassConst('A', 'B');"],
        ];
    }

    /**
     * @dataProvider newInstances
     */
    public function testNewInstance($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function newInstances()
    {
        return [
            ['new A', "\Psy\Sudo::newInstance('A');"],
            ['new A($b)', "\Psy\Sudo::newInstance('A', \$b);"],
            ["new A(\$b, 'c')", "\Psy\Sudo::newInstance('A', \$b, 'c');"],
            ["new \$a(\$b, 'c')", "\Psy\Sudo::newInstance(\$a, \$b, 'c');"],
        ];
    }
}
