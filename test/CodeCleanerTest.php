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

    public function testLooksLikeActionWithAssignment()
    {
        $cc = new CodeCleaner();
        $this->assertTrue($cc->codeLooksLikeAction(['$x = new stdClass()']));
        $this->assertTrue($cc->codeLooksLikeAction(['$x += 5']));
        $this->assertTrue($cc->codeLooksLikeAction(['$x[] = 42']));
    }

    public function testLooksLikeActionWithMethodCalls()
    {
        $cc = new CodeCleaner();
        $this->assertTrue($cc->codeLooksLikeAction(['$obj->setName("test")']));
        $this->assertTrue($cc->codeLooksLikeAction(['$model->save()']));
        $this->assertTrue($cc->codeLooksLikeAction(['$obj->set_name("test")']));
    }

    public function testLooksLikeInspectionWithVariable()
    {
        $cc = new CodeCleaner();
        $this->assertFalse($cc->codeLooksLikeAction(['$x']));
        $this->assertFalse($cc->codeLooksLikeAction(['$obj->property']));
        $this->assertFalse($cc->codeLooksLikeAction(['$obj["foo"][$bar]']));
        $this->assertFalse($cc->codeLooksLikeAction(['$x ?? []']));
    }

    public function testLooksLikeInspectionWithGetters()
    {
        $cc = new CodeCleaner();
        $this->assertFalse($cc->codeLooksLikeAction(['$obj->getName()']));
        $this->assertFalse($cc->codeLooksLikeAction(['User::find(1)']));
        $this->assertFalse($cc->codeLooksLikeAction(['$x->isValid()']));
        $this->assertFalse($cc->codeLooksLikeAction(['$x->toArray()']));
        $this->assertFalse($cc->codeLooksLikeAction(['$x->asString()']));
        $this->assertFalse($cc->codeLooksLikeAction(['$x->is_valid()']));
        $this->assertFalse($cc->codeLooksLikeAction(['$x->to_array()']));
    }

    public function testPrefixMatchingAvoidsFalsePositives()
    {
        $cc = new CodeCleaner();
        // These should NOT match "is", "to", "as" prefixes, and since they don't
        // match inspection prefixes, they're treated as actions
        $this->assertTrue($cc->codeLooksLikeAction(['$x->issue()']));
        $this->assertTrue($cc->codeLooksLikeAction(['$x->top()']));
        $this->assertTrue($cc->codeLooksLikeAction(['$x->asset()']));
        $this->assertTrue($cc->codeLooksLikeAction(['$x->total()']));
    }

    public function testActionDetectionWithNamespace()
    {
        $cc = new CodeCleaner();
        // Code within a namespace should still be detected correctly
        $this->assertTrue($cc->codeLooksLikeAction(['namespace Foo; $x = 1']));
        $this->assertFalse($cc->codeLooksLikeAction(['namespace Foo; $x']));
        $this->assertTrue($cc->codeLooksLikeAction(['namespace Foo; $obj->setName("test")']));
        $this->assertFalse($cc->codeLooksLikeAction(['namespace Foo; $obj->getName()']));
    }
}
