<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use PHPUnit\Framework\MockObject\MockObject;
use Psy\CodeCleaner;
use Psy\CodeCleaner\NoReturnValue;
use Psy\Command\ReflectingCommand;
use Psy\Context;
use Psy\Exception\ErrorException;
use Psy\Exception\RuntimeException;
use Psy\Reflection\ReflectionConstant;
use Psy\Shell;
use Psy\Util\Mirror;

/**
 * @group isolation-fail
 */
class ReflectingCommandTest extends \Psy\Test\TestCase
{
    private TestableReflectingCommand $command;
    /** @var Shell&MockObject */
    private Shell $shell;
    private Context $context;
    private CodeCleaner $cleaner;

    protected function setUp(): void
    {
        $this->context = new Context();
        $this->cleaner = new CodeCleaner();
        $this->shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['execute', 'getNamespace', 'getBoundClass', 'getBoundObject'])
            ->getMock();

        $this->command = new TestableReflectingCommand();
        $this->command->setApplication($this->shell);
        $this->command->setContext($this->context);
        $this->command->setCodeCleaner($this->cleaner);
    }

    /**
     * @dataProvider targetPatterns
     */
    public function testGetTargetParsesPatterns(string $input, string $expectedPattern)
    {
        // Just verify the regex patterns match correctly
        $this->assertMatchesRegularExpression($expectedPattern, $input);
    }

    public function targetPatterns(): array
    {
        return [
            // CLASS_OR_FUNC pattern
            ['DateTime', ReflectingCommand::CLASS_OR_FUNC],
            ['Psy\\Shell', ReflectingCommand::CLASS_OR_FUNC],
            ['\\Psy\\Shell', ReflectingCommand::CLASS_OR_FUNC],
            ['array_map', ReflectingCommand::CLASS_OR_FUNC],

            // CLASS_MEMBER pattern
            ['DateTime::format', ReflectingCommand::CLASS_MEMBER],
            ['Psy\\Shell::debug', ReflectingCommand::CLASS_MEMBER],
            ['\\Psy\\Shell::run', ReflectingCommand::CLASS_MEMBER],

            // CLASS_STATIC pattern
            ['DateTime::$timezone', ReflectingCommand::CLASS_STATIC],
            ['Psy\\Shell::$instance', ReflectingCommand::CLASS_STATIC],

            // INSTANCE_MEMBER pattern
            ['$foo->bar', ReflectingCommand::INSTANCE_MEMBER],
            ['$foo->method', ReflectingCommand::INSTANCE_MEMBER],
            ['$foo::CONST', ReflectingCommand::INSTANCE_MEMBER],
            ['$foo::method', ReflectingCommand::INSTANCE_MEMBER],
        ];
    }

    public function testGetTargetWithClassName()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$target, $member, $kind] = $this->command->doGetTarget('DateTime');

        $this->assertEquals('DateTime', $target);
        $this->assertNull($member);
        $this->assertEquals(0, $kind);
    }

    public function testGetTargetWithFullyQualifiedClassName()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$target, $member, $kind] = $this->command->doGetTarget('\\DateTime');

        $this->assertEquals('\\DateTime', $target);
        $this->assertNull($member);
        $this->assertEquals(0, $kind);
    }

    public function testGetTargetWithClassMember()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$target, $member, $kind] = $this->command->doGetTarget('DateTime::format');

        $this->assertEquals('DateTime', $target);
        $this->assertEquals('format', $member);
        $this->assertEquals(Mirror::CONSTANT | Mirror::METHOD, $kind);
    }

    public function testGetTargetWithClassStaticProperty()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$target, $member, $kind] = $this->command->doGetTarget('ReflectionMethod::$class');

        $this->assertEquals('ReflectionMethod', $target);
        $this->assertEquals('class', $member);
        $this->assertEquals(Mirror::STATIC_PROPERTY | Mirror::PROPERTY, $kind);
    }

    public function testGetTargetWithInstanceMember()
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $this->context->setAll(['myObj' => $obj]);

        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('execute')->willReturn($obj);

        [$target, $member, $kind] = $this->command->doGetTarget('$myObj->foo');

        $this->assertSame($obj, $target);
        $this->assertEquals('foo', $member);
        $this->assertEquals(Mirror::METHOD | Mirror::PROPERTY, $kind);
    }

    public function testGetTargetWithInstanceStaticMember()
    {
        $obj = new \DateTime();
        $this->context->setAll(['myObj' => $obj]);

        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('execute')->willReturn($obj);

        [$target, $member, $kind] = $this->command->doGetTarget('$myObj::ATOM');

        $this->assertSame($obj, $target);
        $this->assertEquals('ATOM', $member);
        $this->assertEquals(Mirror::CONSTANT | Mirror::METHOD, $kind);
    }

    public function testResolveNameWithSelf()
    {
        $this->shell->method('getBoundClass')->willReturn('Psy\\Shell');
        $this->shell->method('getBoundObject')->willReturn(null);
        $this->shell->method('getNamespace')->willReturn(null);

        $result = $this->command->doResolveName('self');

        $this->assertEquals('Psy\\Shell', $result);
    }

    public function testResolveNameWithStatic()
    {
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(new \DateTime());
        $this->shell->method('getNamespace')->willReturn(null);

        $result = $this->command->doResolveName('static');

        $this->assertEquals('DateTime', $result);
    }

    public function testResolveNameWithSelfThrowsWhenNoClassScope()
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Cannot use "self" when no class scope is active');

        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);
        $this->shell->method('getNamespace')->willReturn(null);

        $this->command->doResolveName('self');
    }

    public function testResolveNameWithStaticThrowsWhenNoClassScope()
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Cannot use "static" when no class scope is active');

        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);
        $this->shell->method('getNamespace')->willReturn(null);

        $this->command->doResolveName('static');
    }

    public function testResolveNameWithFullyQualifiedName()
    {
        $this->shell->method('getNamespace')->willReturn('SomeNamespace');
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        $result = $this->command->doResolveName('\\DateTime');

        $this->assertEquals('\\DateTime', $result);
    }

    public function testResolveNameWithNamespace()
    {
        $this->shell->method('getNamespace')->willReturn('Psy');
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        $result = $this->command->doResolveName('Shell');

        $this->assertEquals('Psy\\Shell', $result);
    }

    public function testResolveNameWithNamespaceForFunction()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        $result = $this->command->doResolveName('array_map', true);

        $this->assertEquals('array_map', $result);
    }

    public function testGetTargetAndReflectorWithClass()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$value, $reflector] = $this->command->doGetTargetAndReflector('DateTime');

        $this->assertEquals('DateTime', $value);
        $this->assertInstanceOf(\ReflectionClass::class, $reflector);
        $this->assertEquals('DateTime', $reflector->getName());
    }

    public function testGetTargetAndReflectorWithMethod()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$value, $reflector] = $this->command->doGetTargetAndReflector('DateTime::format');

        $this->assertEquals('DateTime', $value);
        $this->assertInstanceOf(\ReflectionMethod::class, $reflector);
        $this->assertEquals('format', $reflector->getName());
    }

    public function testGetTargetAndReflectorWithFunction()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$value, $reflector] = $this->command->doGetTargetAndReflector('array_map');

        $this->assertEquals('array_map', $value);
        $this->assertInstanceOf(\ReflectionFunction::class, $reflector);
        $this->assertEquals('array_map', $reflector->getName());
    }

    public function testGetTargetAndReflectorWithConstant()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$value, $reflector] = $this->command->doGetTargetAndReflector('PHP_VERSION');

        $this->assertEquals('PHP_VERSION', $value);
        $this->assertInstanceOf(ReflectionConstant::class, $reflector);
    }

    public function testGetTargetAndReflectorWithClassConstant()
    {
        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        [$value, $reflector] = $this->command->doGetTargetAndReflector('DateTime::ATOM');

        $this->assertEquals('DateTime', $value);
        $this->assertInstanceOf(\ReflectionClassConstant::class, $reflector);
        $this->assertEquals('ATOM', $reflector->getName());
    }

    public function testResolveCodeThrowsForUnknownTarget()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown target');

        $this->shell->method('execute')->willReturn(new NoReturnValue());

        $this->command->doResolveCode('$nonExistentVariable');
    }

    public function testGetScopeVariable()
    {
        $this->context->setAll(['testVar' => 'testValue']);

        $result = $this->command->doGetScopeVariable('testVar');

        $this->assertEquals('testValue', $result);
    }

    public function testGetScopeVariables()
    {
        $this->context->setAll(['foo' => 1, 'bar' => 2]);
        $this->context->setReturnValue('lastReturn');

        $result = $this->command->doGetScopeVariables();

        $this->assertArrayHasKey('foo', $result);
        $this->assertArrayHasKey('bar', $result);
        $this->assertArrayHasKey('_', $result);
        $this->assertEquals(1, $result['foo']);
        $this->assertEquals(2, $result['bar']);
        $this->assertEquals('lastReturn', $result['_']);
    }

    public function testSetCommandScopeVariablesWithReflectionClass()
    {
        $reflector = new \ReflectionClass('DateTime');

        $this->command->doSetCommandScopeVariables($reflector);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('DateTime', $vars['__class']);
        $this->assertArrayNotHasKey('__namespace', $vars);
    }

    public function testSetCommandScopeVariablesWithNamespacedClass()
    {
        $reflector = new \ReflectionClass('Psy\\Shell');

        $this->command->doSetCommandScopeVariables($reflector);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('Psy\\Shell', $vars['__class']);
        $this->assertEquals('Psy', $vars['__namespace']);
        $this->assertArrayHasKey('__file', $vars);
        $this->assertArrayHasKey('__dir', $vars);
    }

    public function testSetCommandScopeVariablesWithReflectionMethod()
    {
        $reflector = new \ReflectionMethod('DateTime', 'format');

        $this->command->doSetCommandScopeVariables($reflector);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('DateTime::format', $vars['__method']);
        $this->assertEquals('DateTime', $vars['__class']);
    }

    public function testSetCommandScopeVariablesWithReflectionFunction()
    {
        $reflector = new \ReflectionFunction('array_map');

        $this->command->doSetCommandScopeVariables($reflector);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('array_map', $vars['__function']);
        $this->assertArrayNotHasKey('__namespace', $vars);
    }

    public function testSetCommandScopeVariablesWithReflectionProperty()
    {
        $reflector = new \ReflectionProperty('ReflectionClass', 'name');

        $this->command->doSetCommandScopeVariables($reflector);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('ReflectionClass', $vars['__class']);
    }

    public function testSetCommandScopeVariablesWithReflectionClassConstant()
    {
        $reflector = new \ReflectionClassConstant('DateTime', 'ATOM');

        $this->command->doSetCommandScopeVariables($reflector);

        $vars = $this->context->getCommandScopeVariables();
        // ATOM is declared on DateTimeInterface, which DateTime implements
        $this->assertEquals('DateTimeInterface', $vars['__class']);
    }

    public function testSetCommandScopeVariablesWithReflectionConstant()
    {
        $reflector = new ReflectionConstant('PHP_VERSION');

        $this->command->doSetCommandScopeVariables($reflector);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertArrayNotHasKey('__namespace', $vars);
    }

    public function testContextAwareInterface()
    {
        $this->assertInstanceOf(\Psy\ContextAware::class, $this->command);
    }

    public function testCodeCleanerAwareInterface()
    {
        $this->assertInstanceOf(\Psy\CodeCleanerAware::class, $this->command);
    }
}
