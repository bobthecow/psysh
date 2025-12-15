<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\ExecutionLoop;

use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter;
use Psy\ExecutionLoop\UopzReloaderVisitor;
use Psy\ParserFactory;
use Psy\Test\TestCase;

/**
 * @group isolation-fail
 */
class UopzReloaderVisitorTest extends TestCase
{
    private $parser;
    private $printer;

    public function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory())->createParser();
        $this->printer = new PrettyPrinter\Standard();
    }

    public function testNoSkipsForSimpleClass()
    {
        // Note: Visitor warns about new classes that don't exist yet,
        // but this isn't a "skip" - it's just informational
        $code = '<?php
class SimpleClass {
    public function getValue() {
        return "value";
    }
}';
        $visitor = $this->visitCode($code);

        // No skips (conditional code)
        $this->assertFalse($visitor->hasSkips());
    }

    public function testWarnsOnConditionalFunction()
    {
        $code = '<?php
if (true) {
    function conditionalFunc() {
        return "hello";
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertTrue($visitor->hasWarnings());
        $this->assertTrue($visitor->hasSkips());
        $this->assertStringContainsString('Skipped conditional', $visitor->getWarnings()[0]);
        $this->assertStringContainsString('conditionalFunc', $visitor->getWarnings()[0]);
    }

    public function testConditionalFunctionInsideIfIsSkipped()
    {
        // Functions inside if blocks should be detected as conditional
        $code = '<?php
if (defined("FOO")) {
    function conditionalInIf() {
        return true;
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertTrue($visitor->hasSkips());
        $this->assertStringContainsString('Skipped conditional', $visitor->getWarnings()[0]);
    }

    public function testWarnsOnStaticVariables()
    {
        // Define the function first so the visitor sees it as existing
        if (!\function_exists('funcWithStatic')) {
            eval('function funcWithStatic() { return 0; }');
        }

        $code = '<?php
function funcWithStatic() {
    static $counter = 0;
    return ++$counter;
}';
        $visitor = $this->visitCode($code);

        $this->assertTrue($visitor->hasWarnings());
        $this->assertStringContainsString('Static vars will reset', $visitor->getWarnings()[0]);
    }

    public function testWarnsOnStaticVariablesInMethod()
    {
        // Define the class first so the visitor can check method details
        if (!\class_exists('ClassWithStaticVar', false)) {
            eval('class ClassWithStaticVar { public function counter() { return 0; } }');
        }

        $code = '<?php
class ClassWithStaticVar {
    public function counter() {
        static $count = 0;
        return ++$count;
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertTrue($visitor->hasWarnings());
        $warnings = \implode("\n", $visitor->getWarnings());
        $this->assertStringContainsString('Static vars will reset', $warnings);
    }

    public function testWarnsOnTopLevelSideEffects()
    {
        $code = '<?php
$result = doSomething();';

        $visitor = $this->visitCode($code);

        $this->assertTrue($visitor->hasWarnings());
        $this->assertStringContainsString('Not re-run', $visitor->getWarnings()[0]);
    }

    public function testWarnsOnTopLevelEcho()
    {
        $code = '<?php
echo "Hello world";';

        $visitor = $this->visitCode($code);

        $this->assertTrue($visitor->hasWarnings());
        $this->assertStringContainsString('Not re-run', $visitor->getWarnings()[0]);
    }

    public function testForceReloadBypassesConditionalSkip()
    {
        $code = '<?php
if (true) {
    function bypassedFunc() {
        return "hello";
    }
}';
        // Use forceReload to bypass all
        $visitor = $this->visitCode($code, true);

        $this->assertTrue($visitor->hasWarnings());
        $this->assertFalse($visitor->hasSkips()); // Not skipped due to bypass
        $this->assertStringContainsString('YOLO: Force-reloaded', $visitor->getWarnings()[0]);
    }

    public function testNoSkipsForUnconditionalCode()
    {
        $code = '<?php
function normalFunc() {
    return "hello";
}

const NORMAL_CONST = 123;

class NormalClass {
    const CLASS_CONST = "value";

    public function method() {
        return true;
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertFalse($visitor->hasSkips());
    }

    public function testNestedConditionalIsDetected()
    {
        $code = '<?php
if (true) {
    if (false) {
        function deeplyNested() {
            return "deep";
        }
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertTrue($visitor->hasSkips());
    }

    public function testFunctionInsideClassIsNotConditional()
    {
        // Functions inside class methods should not be considered conditional
        // (though they would be odd, they're not conditional on runtime state)
        $code = '<?php
class Outer {
    public function createFunc() {
        // This is weird but not conditional at file-level
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertFalse($visitor->hasSkips());
    }

    public function testConditionalFunctionInsideForIsSkipped()
    {
        // Functions inside for loops should be detected as conditional
        $code = '<?php
for ($i = 0; $i < 1; $i++) {
    function conditionalInFor() {
        return true;
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertTrue($visitor->hasSkips());
        $this->assertStringContainsString('Skipped conditional', $visitor->getWarnings()[0]);
    }

    public function testAnonymousClassIsHandled()
    {
        // Anonymous classes should not cause errors
        $code = '<?php
$obj = new class {
    public function getValue() {
        return "anonymous";
    }
};';
        $visitor = $this->visitCode($code);

        // Should warn about the expression (side effect) but not crash
        $this->assertTrue($visitor->hasWarnings());
        $this->assertFalse($visitor->hasSkips());
    }

    public function testMultipleConstantsInSingleDeclaration()
    {
        // Multiple constants in one statement should all be processed
        $code = '<?php
const FOO = 1, BAR = 2, BAZ = 3;

class MultiConst {
    const A = "a", B = "b";
}';
        $visitor = $this->visitCode($code);

        // No skips for unconditional constants
        $this->assertFalse($visitor->hasSkips());
    }

    public function testUnionReturnType()
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Union types require PHP 8.0+');
        }

        $code = '<?php
class UnionTest {
    public function getValue(): int|string {
        return "test";
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertFalse($visitor->hasSkips());
    }

    public function testNullableReturnType()
    {
        // Nullable types are supported in PHP 7.1+
        $code = '<?php
class NullableTest {
    public function getValue(): ?string {
        return null;
    }
}';
        $visitor = $this->visitCode($code);

        $this->assertFalse($visitor->hasSkips());
    }

    public function testPhp8Attributes()
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Attributes require PHP 8.0+');
        }

        $code = '<?php
#[Attribute]
class MyAttribute {}

class WithAttributes {
    #[MyAttribute]
    public function decorated(): void {}
}';
        $visitor = $this->visitCode($code);

        // Attributes should not cause errors or skips
        $this->assertFalse($visitor->hasSkips());
    }

    public function testEnumConstants()
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enums require PHP 8.1+');
        }

        $code = '<?php
enum Status: string {
    case Pending = "pending";
    case Active = "active";
    case Closed = "closed";
}';
        $visitor = $this->visitCode($code);

        // Enums should be handled (we can't reload them, but shouldn't error)
        $this->assertFalse($visitor->hasSkips());
    }

    public function testIntersectionType()
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Intersection types require PHP 8.1+');
        }

        $code = '<?php
interface A {}
interface B {}

class IntersectionTest {
    public function process(A&B $obj): void {}
}';
        $visitor = $this->visitCode($code);

        $this->assertFalse($visitor->hasSkips());
    }

    private function visitCode(string $code, bool $forceReload = false): UopzReloaderVisitor
    {
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser();
        $visitor = new UopzReloaderVisitor($this->printer, $forceReload);
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }
}
