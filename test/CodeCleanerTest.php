<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\CodeCleaner;

class CodeCleanerTest extends TestCase
{
    /**
     * @dataProvider semicolonCodeProvider
     */
    public function testAutomaticSemicolons(array $lines, $requireSemicolons, $expected)
    {
        $cc = new CodeCleaner();
        $this->assertSame($expected, $cc->clean($lines, $requireSemicolons));
    }

    public function semicolonCodeProvider()
    {
        return [
            [['true'],  false, 'return true;'],
            [['true;'], false, 'return true;'],
            [['true;'], true,  'return true;'],
            [['true'],  true,  false],

            [['echo "foo";', 'true'], true,  false],

            [['echo "foo";', 'true'], false, "echo \"foo\";\nreturn true;"],
        ];
    }

    /**
     * @dataProvider unclosedStatementsProvider
     */
    public function testUnclosedStatements(array $lines, $isUnclosed)
    {
        $cc = new CodeCleaner();
        $res = $cc->clean($lines);

        if ($isUnclosed) {
            $this->assertFalse($res);
        } else {
            $this->assertNotFalse($res);
        }
    }

    public function unclosedStatementsProvider()
    {
        return [
            [['echo "'],   true],
            [['echo \''],  true],
            [['if (1) {'], true],

            [['echo "foo",'], true],

            [['echo ""'],   false],
            [["echo ''"],   false],
            [['if (1) {}'], false],

            [['// closed comment'],    false],
            [['function foo() { /**'], true],

            [['var_dump(1, 2,'], true],
            [['var_dump(1, 2,', '3)'], false],
        ];
    }

    /**
     * @dataProvider moreUnclosedStatementsProvider
     */
    public function testMoreUnclosedStatements(array $lines)
    {
        $cc = new CodeCleaner();
        $res = $cc->clean($lines);

        $this->assertFalse($res);
    }

    public function moreUnclosedStatementsProvider()
    {
        return [
            [["\$content = <<<EOS\n"]],
            [["\$content = <<<'EOS'\n"]],

            [['/* unclosed comment']],
            [['/** unclosed comment']],
        ];
    }

    /**
     * @dataProvider invalidStatementsProvider
     */
    public function testInvalidStatementsThrowParseErrors($code)
    {
        $this->expectException(\Psy\Exception\ParseErrorException::class);

        $cc = new CodeCleaner();
        $cc->clean([$code]);

        $this->fail();
    }

    public function invalidStatementsProvider()
    {
        return [
            ['function "what'],
            ["function 'what"],
            ['echo }'],
            ['echo {'],
            ['if (1) }'],
            ['echo """'],
            ["echo '''"],
            ['$foo "bar'],
            ['$foo \'bar'],
        ];
    }

    public function testNamespaceReEntryResetsUseStatements()
    {
        $cc = new CodeCleaner();

        // Enter namespace A and add a use statement
        $cc->clean(['namespace A;']);
        $cc->clean(['use StdClass as Foo;']);

        // Re-enter namespace A - should clear previous use statements
        $cc->clean(['namespace A;']);

        // Should be able to use same alias for a different class
        $result = $cc->clean(['use DateTime as Foo;']);
        $this->assertNotFalse($result);
        $this->assertStringContainsString('DateTime', $result);
    }

    public function testGlobalNamespaceReEntryResetsUseStatements()
    {
        $cc = new CodeCleaner();

        // Add use statement in global namespace
        $cc->clean(['use StdClass as Bar;']);

        // Enter braced global namespace - should clear previous use statements
        $cc->clean(['namespace {}']);

        // Should be able to use same alias for a different class
        $result = $cc->clean(['use DateTime as Bar;']);
        $this->assertNotFalse($result);
        $this->assertStringContainsString('DateTime', $result);
    }

    public function testUseStatementsPersistWithinNamespace()
    {
        $cc = new CodeCleaner();

        // Enter namespace and add use statement
        $cc->clean(['namespace Foo;']);
        $cc->clean(['use StdClass as Bar;']);

        // Execute code without namespace declaration - use statement should persist
        // and code should be wrapped in the namespace
        $result = $cc->clean(['$x = new Bar();']);
        $this->assertNotFalse($result);
        $this->assertStringContainsString('namespace Foo', $result);

        // The use statement should persist for resolveClassName
        $resolved = $cc->resolveClassName('Bar');
        $this->assertSame('\\StdClass', $resolved);
    }
}
