<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Context;
use Psy\Shell;

class ContextTest extends TestCase
{
    public function testGet()
    {
        $this->assertTrue(true);
    }

    public function testGetAll()
    {
        $this->assertTrue(true);
    }

    public function testGetSpecialVariables()
    {
        $context = new Context();

        $this->assertNull($context->get('_'));
        $this->assertNull($context->getReturnValue());

        $this->assertSame(['_' => null], $context->getAll());

        $e = new \Exception('eeeeeee');
        $obj = new \stdClass();
        $context->setLastException($e);
        $context->setLastStdout('out');
        $context->setBoundObject($obj);

        $context->setCommandScopeVariables([
            '__function'  => 'function',
            '__method'    => 'method',
            '__class'     => 'class',
            '__namespace' => 'namespace',
            '__file'      => 'file',
            '__line'      => 'line',
            '__dir'       => 'dir',
        ]);

        $expected = [
            '_'           => null,
            '_e'          => $e,
            '__out'       => 'out',
            'this'        => $obj,
            '__function'  => 'function',
            '__method'    => 'method',
            '__class'     => 'class',
            '__namespace' => 'namespace',
            '__file'      => 'file',
            '__line'      => 'line',
            '__dir'       => 'dir',
        ];

        $this->assertSame($expected, $context->getAll());
    }

    public function testSetAll()
    {
        $context = new Context();

        $baz = new \stdClass();
        $vars = [
            'foo' => 'Foo',
            'bar' => 123,
            'baz' => $baz,

            '_'         => 'fail',
            '_e'        => 'fail',
            '__out'     => 'fail',
            'this'      => 'fail',
            '__psysh__' => 'fail',

            '__function'  => 'fail',
            '__method'    => 'fail',
            '__class'     => 'fail',
            '__namespace' => 'fail',
            '__file'      => 'fail',
            '__line'      => 'fail',
            '__dir'       => 'fail',
        ];

        $context->setAll($vars);

        $this->assertSame('Foo', $context->get('foo'));
        $this->assertSame(123, $context->get('bar'));
        $this->assertSame($baz, $context->get('baz'));

        $this->assertSame(['foo' => 'Foo', 'bar' => 123, 'baz' => $baz, '_' => null], $context->getAll());
    }

    /**
     * @dataProvider specialNames
     */
    public function testSetAllDoesNotSetSpecial($name)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown variable: \$\w+/');

        $context = new Context();
        $context->setAll([$name => 'fail']);
        $context->get($name);

        $this->fail();
    }

    public function specialNames()
    {
        return [
            ['_e'],
            ['__out'],
            ['this'],
            ['__psysh__'],
            ['__function'],
            ['__method'],
            ['__class'],
            ['__namespace'],
            ['__file'],
            ['__line'],
            ['__dir'],
        ];
    }

    public function testReturnValue()
    {
        $context = new Context();
        $this->assertNull($context->getReturnValue());

        $val = 'some string';
        $context->setReturnValue($val);
        $this->assertSame($val, $context->getReturnValue());
        $this->assertSame($val, $context->get('_'));

        $obj = new \stdClass();
        $context->setReturnValue($obj);
        $this->assertSame($obj, $context->getReturnValue());
        $this->assertSame($obj, $context->get('_'));

        $context->setReturnValue(null);
        $this->assertNull($context->getReturnValue());
    }

    public function testLastException()
    {
        $context = new Context();
        $e = new \Exception('wat');
        $context->setLastException($e);
        $this->assertSame($e, $context->getLastException());
        $this->assertSame($e, $context->get('_e'));
    }

    public function testLastExceptionThrowsSometimes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No most-recent exception');

        $context = new Context();
        $context->getLastException();

        $this->fail();
    }

    public function testLastStdout()
    {
        $context = new Context();
        $context->setLastStdout('ouuuuut');
        $this->assertSame('ouuuuut', $context->getLastStdout());
        $this->assertSame('ouuuuut', $context->get('__out'));
    }

    public function testLastStdoutThrowsSometimes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No most-recent output');

        $context = new Context();
        $context->getLastStdout();

        $this->fail();
    }

    public function testBoundObject()
    {
        $context = new Context();
        $this->assertNull($context->getBoundObject());

        $obj = new \stdClass();
        $context->setBoundObject($obj);
        $this->assertSame($obj, $context->getBoundObject());
        $this->assertSame($obj, $context->get('this'));

        $context->setBoundObject(null);
        $this->assertNull($context->getBoundObject());
    }

    public function testBoundObjectThrowsSometimes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown variable: $this');

        $context = new Context();
        $context->get('this');

        $this->fail();
    }

    public function testBoundClass()
    {
        $context = new Context();
        $this->assertNull($context->getBoundClass());

        $context->setBoundClass('');
        $this->assertNull($context->getBoundClass());

        $context->setBoundClass(Shell::class);
        $this->assertSame(Shell::class, $context->getBoundClass());

        $context->setBoundObject(new \stdClass());
        $this->assertNotNull($context->getBoundObject());
        $this->assertNull($context->getBoundClass());

        $context->setBoundClass(Shell::class);
        $this->assertSame(Shell::class, $context->getBoundClass());
        $this->assertNull($context->getBoundObject());

        $context->setBoundClass(null);
        $this->assertNull($context->getBoundClass());
        $this->assertNull($context->getBoundObject());
    }

    public function testCommandScopeVariables()
    {
        $__function = 'donkey';
        $__method = 'diddy';
        $__class = 'cranky';
        $__namespace = 'funky';
        $__file = 'candy';
        $__line = 'dixie';
        $__dir = 'wrinkly';

        $vars = \compact('__function', '__method', '__class', '__namespace', '__file', '__line', '__dir');

        $context = new Context();
        $context->setCommandScopeVariables($vars);

        $this->assertSame($vars, $context->getCommandScopeVariables());

        $this->assertSame($__function, $context->get('__function'));
        $this->assertSame($__method, $context->get('__method'));
        $this->assertSame($__class, $context->get('__class'));
        $this->assertSame($__namespace, $context->get('__namespace'));
        $this->assertSame($__file, $context->get('__file'));
        $this->assertSame($__line, $context->get('__line'));
        $this->assertSame($__dir, $context->get('__dir'));

        $someVars = \compact('__function', '__namespace', '__file', '__line', '__dir');
        $context->setCommandScopeVariables($someVars);
    }

    public function testGetUnusedCommandScopeVariableNames()
    {
        $context = new Context();

        $this->assertSame(
            ['__function', '__method', '__class', '__namespace', '__file', '__line', '__dir'],
            $context->getUnusedCommandScopeVariableNames()
        );

        $context->setCommandScopeVariables([
            '__function'  => 'foo',
            '__namespace' => 'bar',
            '__file'      => 'baz',
            '__line'      => 123,
            '__dir'       => 'qux',
        ]);

        $this->assertSame(
            ['__method', '__class'],
            \array_values($context->getUnusedCommandScopeVariableNames())
        );
    }

    /**
     * @dataProvider specialAndNotSpecialVariableNames
     */
    public function testIsSpecialVariableName($name, $isSpecial)
    {
        $context = new Context();

        if ($isSpecial) {
            $this->assertTrue($context->isSpecialVariableName($name));
        } else {
            $this->assertFalse($context->isSpecialVariableName($name));
        }
    }

    public function specialAndNotSpecialVariableNames()
    {
        return [
            ['foo', false],
            ['psysh', false],
            ['__psysh', false],

            ['_', true],
            ['_e', true],
            ['__out', true],
            ['this', true],
            ['__psysh__', true],

            ['__function', true],
            ['__method', true],
            ['__class', true],
            ['__namespace', true],
            ['__file', true],
            ['__line', true],
            ['__dir', true],
        ];
    }
}
