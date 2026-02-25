<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Input;

use Psy\Readline\Interactive\Input\Buffer;
use Psy\Test\TestCase;

/**
 * Test that invalid input is handled correctly.
 */
class InvalidInputTest extends TestCase
{
    /**
     * Test that genuine syntax errors are treated as complete (to show error).
     *
     * @dataProvider getInvalidSyntaxExamples
     */
    public function testInvalidSyntaxIsComplete(string $code, string $description): void
    {
        $buffer = new Buffer();
        $buffer->setText($code);

        $this->assertTrue(
            $buffer->isCompleteStatement(),
            "Invalid syntax should be complete to show error immediately: $description"
        );
    }

    /**
     * Test that incomplete but potentially valid code is not complete.
     *
     * @dataProvider getIncompleteButValidExamples
     */
    public function testIncompleteButValidIsNotComplete(string $code, string $description): void
    {
        $buffer = new Buffer();
        $buffer->setText($code);

        $this->assertFalse(
            $buffer->isCompleteStatement(),
            "Incomplete but potentially valid code should wait for more input: $description"
        );
    }

    /**
     * @return array Test cases for invalid syntax
     */
    public function getInvalidSyntaxExamples(): array
    {
        return [
            // Genuine syntax errors that can never be valid
            ['function function', 'double function keyword'],
            ['class class', 'double class keyword'],
            ['if if', 'double if keyword'],
            ['for for', 'double for keyword'],
            ['$x = = 5', 'double assignment operator'],
            ['echo echo', 'double echo keyword'],
            ['return return', 'double return keyword'],
            ['new new', 'double new keyword'],
            ['use use', 'double use keyword'],
            ['namespace namespace', 'double namespace keyword'],

            // Malformed constructs
            ['if (true)) {', 'extra closing parenthesis'],
            ['function foo(($x) {', 'double opening parenthesis'],
            ['class Foo {{', 'double opening brace'],
            ['$x = [1, 2, 3]]', 'extra closing bracket'],

            // Invalid operators
            ['5 ++ 5', 'invalid operator spacing'],
            ['$x =>', 'arrow operator without array context'],

            // These should execute to show parse error
            ['if (5 5)', 'missing operator between numbers'],
            ['echo "hello" "world"', 'missing concatenation operator'],
            ['function foo() }', 'missing opening brace'],
            ['class Foo ]', 'wrong bracket type'],
        ];
    }

    /**
     * @return array Test cases for incomplete but valid code
     */
    public function getIncompleteButValidExamples(): array
    {
        return [
            // These should wait for more input
            ['if (true', 'unclosed if condition'],
            ['if (true)', 'if without body, waits for more even though technically valid'],
            ['while (false)', 'while without body, waits for more'],
            ['for (;;)', 'for without body, waits for more'],
            ['foreach ($arr as $val)', 'foreach without body, waits for more'],
            ['if (true) {} elseif (false)', 'elseif without body after if, waits for more'],
            ['if (true) {} else', 'else without body after if, waits for more'],
            ['function foo(', 'unclosed function parameters'],
            ['function foo() {', 'unclosed function body'],
            ['class Foo {', 'unclosed class body'],
            ['$x = [', 'unclosed array'],
            ['$x = [1, 2', 'unclosed array with elements'],
            ['echo "hello', 'unclosed string'],
            ['for ($i = 0; $i < 10', 'unclosed for loop'],
            ['while (true', 'unclosed while condition'],
            ['try {', 'unclosed try block'],
            ['switch ($x) {', 'unclosed switch'],

            // Trailing operators (should wait for more)
            ['$x = 5 +', 'trailing plus operator'],
            ['$x = 5 *', 'trailing multiplication operator'],
            ['$x = $y .', 'trailing concatenation operator'],
            ['$x = $y &&', 'trailing logical operator'],
            ['$x = $y ||', 'trailing logical operator'],

            // Variable names that could be completed
            ['$', 'dollar sign alone, could be start of variable'],
            ['$$', 'double dollar sign, could be variable variable'],

            // Special syntax
            ['echo <<<EOT', 'unclosed heredoc'],

            // Comments (wait for closure)
            ['/**', 'unclosed doc comment'],
            ['/*', 'unclosed block comment'],

            // Multi-line constructs
            ['$x = function() {', 'unclosed closure'],
            ['$x = new class {', 'unclosed anonymous class'],
        ];
    }

    /**
     * Test control structures without bodies wait for more input.
     *
     * Even though `if (true)` is technically valid PHP, we treat it as incomplete
     * for better UX, nobody types these intentionally in a REPL.
     */
    public function testControlStructuresWithoutBodiesAreIncomplete(): void
    {
        $buffer = new Buffer();

        // All of these are technically valid PHP but should wait for bodies
        $testCases = [
            'if (true)'                   => 'if without body',
            'if ($x > 5)'                 => 'if with complex condition',
            'while ($i < 10)'             => 'while without body',
            'for ($i = 0; $i < 10; $i++)' => 'for loop without body',
            'foreach ($array as $item)'   => 'foreach without body',
        ];

        foreach ($testCases as $code => $description) {
            $buffer->setText($code);
            $this->assertFalse(
                $buffer->isCompleteStatement(),
                "Control structure should wait for body: $description"
            );
        }

        // But with a body, they should be complete
        $buffer->setText('if (true) { }');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Control structure with body should be complete'
        );

        $buffer->setText('if (true) echo "hi";');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Control structure with single statement should be complete'
        );
    }

    /**
     * Test if/elseif/else chains wait for bodies.
     */
    public function testIfElseifElseChains(): void
    {
        $buffer = new Buffer();

        // elseif without body should wait
        $buffer->setText('if (true) {} elseif (false)');
        $this->assertFalse(
            $buffer->isCompleteStatement(),
            'elseif after if should wait for body'
        );

        // else without body should wait
        $buffer->setText('if (true) {} else');
        $this->assertFalse(
            $buffer->isCompleteStatement(),
            'else after if should wait for body'
        );

        // Complex chain
        $buffer->setText('if (true) { echo 1; } elseif (false)');
        $this->assertFalse(
            $buffer->isCompleteStatement(),
            'elseif in chain should wait for body'
        );

        // Complete chain
        $buffer->setText('if (true) {} elseif (false) {} else {}');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Complete if/elseif/else chain should be complete'
        );

        // Standalone else/elseif are invalid (will show error)
        $buffer->setText('else');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Standalone else is invalid, should execute to show error'
        );

        $buffer->setText('elseif (true)');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Standalone elseif is invalid, should execute to show error'
        );
    }

    /**
     * Test automatic semicolon insertion doesn't affect invalid syntax.
     */
    public function testAutomaticSemicolonInsertionWithInvalidSyntax(): void
    {
        // Without requireSemicolons (automatic insertion enabled)
        $buffer = new Buffer(false);

        // Invalid syntax should still be complete (to show error)
        $buffer->setText('function function');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Invalid syntax should be complete even with auto semicolon insertion'
        );

        // Valid code without semicolon should be complete
        $buffer->setText('echo "hello"');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Valid code without semicolon should be complete with auto insertion'
        );
    }

    /**
     * Test strict mode (requireSemicolons) with invalid syntax.
     */
    public function testStrictModeWithInvalidSyntax(): void
    {
        // With requireSemicolons (strict mode)
        $buffer = new Buffer(true);

        // Invalid syntax should still be complete (to show error)
        $buffer->setText('function function');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Invalid syntax should be complete even in strict mode'
        );

        // Valid code without semicolon should NOT be complete in strict mode
        $buffer->setText('echo "hello"');
        $this->assertFalse(
            $buffer->isCompleteStatement(),
            'Valid code without semicolon should NOT be complete in strict mode'
        );

        // Valid code with semicolon should be complete
        $buffer->setText('echo "hello";');
        $this->assertTrue(
            $buffer->isCompleteStatement(),
            'Valid code with semicolon should be complete in strict mode'
        );
    }
}
