<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CollisionArity;

if (!\function_exists(__NAMESPACE__.'\\parse')) {
    function parse()
    {
    }
}

namespace Psy\Test;

use Psy\CodeCleaner;

if (!\function_exists(__NAMESPACE__.'\\parse')) {
    function parse($value)
    {
        return $value;
    }
}

/**
 * @group isolation-fail
 */
class CodeCleanerResolveFunctionNameTest extends TestCase
{
    private CodeCleaner $cleaner;

    public function setUp(): void
    {
        $this->cleaner = new CodeCleaner();
    }

    public function testResolveBuiltInFunction()
    {
        $this->assertSame('\\copy', $this->cleaner->resolveFunctionName('copy'));
    }

    public function testResolveUnknownFunction()
    {
        $this->assertNull($this->cleaner->resolveFunctionName('definitely_not_a_real_psysh_test_function'));
    }

    public function testResolveNamespacedFunction()
    {
        $this->cleaner->setNamespace(['Psy', 'Test']);

        $this->assertSame('\\Psy\\Test\\parse', $this->cleaner->resolveFunctionName('parse'));
    }

    public function testResolveAliasedFunction()
    {
        $this->cleaner->clean(['use function Psy\\Test\\parse as parsed;']);

        $this->assertSame('\\Psy\\Test\\parse', $this->cleaner->resolveFunctionName('parsed'));
    }

    public function testResolveAliasedFunctionWhenClassImportUsesSameAlias()
    {
        $this->cleaner->clean(['use ArrayObject as Foo;']);
        $this->cleaner->clean(['use function strlen as Foo;']);

        $this->assertSame('\\strlen', $this->cleaner->resolveFunctionName('Foo'));
    }

    public function testGetCallableFunctionForInput()
    {
        $this->assertSame('\\copy', $this->cleaner->getCallableFunctionForInput('copy ($from, $to)', 'copy'));
        $this->assertNull($this->cleaner->getCallableFunctionForInput('copy ($from)', 'copy'));
        $this->assertNull($this->cleaner->getCallableFunctionForInput('copy ($from, $to)', 'dump'));
        $this->assertNull($this->cleaner->getCallableFunctionForInput('parse (true ? 1 : 2)', 'parse'));
    }

    public function testGetCallableFunctionForInputWithNamespacedFunction()
    {
        $this->cleaner->setNamespace(['Psy', 'Test']);

        $this->assertSame('\\Psy\\Test\\parse', $this->cleaner->getCallableFunctionForInput('parse (true ? 1 : 2)', 'parse'));
    }

    public function testGetCallableFunctionForInputRejectsArityMismatchForNamespacedFunction()
    {
        $this->cleaner->setNamespace(['Psy', 'Test', 'CollisionArity']);

        $this->assertNull($this->cleaner->getCallableFunctionForInput('parse (true ? 1 : 2)', 'parse'));
    }

    public function testGetCallableFunctionForInputIgnoresShellOnlySyntax()
    {
        $this->assertNull($this->cleaner->getCallableFunctionForInput('help --help', 'help'));
        $this->assertNull($this->cleaner->getCallableFunctionForInput('copy src dst', 'copy'));
    }

    public function testResolveFunctionNameFallsBackToGlobalFunctionWhenAliasIsNotFunction()
    {
        $this->cleaner->clean(['use ArrayObject as copy;']);

        $this->assertSame('\\copy', $this->cleaner->resolveFunctionName('copy'));
        $this->assertSame('\\copy', $this->cleaner->getCallableFunctionForInput('copy ($from, $to)', 'copy'));
    }
}
