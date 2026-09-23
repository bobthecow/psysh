<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PhpParser\Node\Expr\FuncCall;
use Psy\CodeCleaner;
use Psy\CodeCleaner\ListPass;
use Psy\Exception\ParseErrorException;

class ListPassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new ListPass());
    }

    /**
     * @dataProvider invalidStatements
     */
    public function testProcessInvalidStatement($code, $expectedMessage)
    {
        $this->expectException(ParseErrorException::class);
        $this->expectExceptionMessage($expectedMessage);

        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);

        $this->fail();
    }

    public function invalidStatements()
    {
        $errorEmptyList = 'Cannot use empty list';
        $errorNonVariableAssign = 'Assignments can only happen to writable values';
        $errorPhpParserSyntax = 'PHP Parse error: ';

        return [
            ['list() = []', $errorEmptyList],
            ['list("a") = [1]', $errorPhpParserSyntax],
            ['list("a" => _) = ["a" => 1]', $errorPhpParserSyntax],
            ['["a"] = [1]', $errorNonVariableAssign],
            ['[] = []', $errorEmptyList],
            ['[,] = [1,2]', $errorEmptyList],
            ['[,,] = [1,2,3]', $errorEmptyList],
            ['[OBJ] = [1]', $errorNonVariableAssign],
            ['[OBJ[0]] = [1]', $errorNonVariableAssign],
            ['[C::OBJ] = [1]', $errorNonVariableAssign],
            ['[C::OBJ[0]] = [1]', $errorNonVariableAssign],
        ];
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessValidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return [
            ['list($a) = [1]'],
            ['list($x, $y) = [1, 2]'],
            ['[$a] = [1]'],
            ['list($b) = [2]'],
            ['[$x, $y] = [1, 2]'],
            ['[$a] = [1]'],
            ['[$x, $y] = [1, 2]'],
            ['["_" => $v] = ["_" => 1]'],
            ['[$a,] = [1,2,3]'],
            ['[,$b] = [1,2,3]'],
            ['[$a,,$c] = [1,2,3]'],
            ['[$a,,,] = [1,2,3]'],
            ['[$a[0], $a[1]] = [1, 2]'],
            ['[$a[0][0][0], $a[0][0][1]] = [1, 2]'],
            ['[$a->b, $a->c] = [1, 2]'],
            ['[$a->b[0], $a->c[1]] = [1, 2]'],
            ['[$a[0]->b[0], $a[0]->c[1]] = [1, 2]'],
            ['[$a[$b->c + $b->d]] = [1]'],
            ['[$a->c()->d, $a->c()->e] = [1, 2]'],
            ['[x()->a, x()->b] = [1, 2]'],
        ];
    }

    /**
     * @dataProvider constantPropertyStatements
     */
    public function testConstantPropertyAssignment($code)
    {
        $stmts = $this->parseConstantPropertyStatement($code);
        $expected = $this->prettyPrint($stmts);

        if (\PHP_VERSION_ID < 80600) {
            $this->expectException(ParseErrorException::class);
            $this->expectExceptionMessage('Assignments can only happen to writable values');
        }

        $this->assertSame($expected, $this->prettyPrint($this->traverse($stmts)));
    }

    public function constantPropertyStatements()
    {
        return [
            ['[OBJ->value] = [1]'],
            ['[OBJ->items[0]] = [1]'],
            ['[OBJ[0]->value] = [1]'],
            ['[C::OBJ->value] = [1]'],
            ['[C::OBJ->items[0]] = [1]'],
            ['[C::OBJ[0]->value] = [1]'],
            ['list(OBJ->value, C::OBJ->value) = [1, 2]'],
        ];
    }

    /**
     * @requires PHP >= 8.6
     */
    public function testCleanedConstantPropertyAssignment()
    {
        if (!\method_exists(FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('Requires PHP-Parser 5.9 or newer.');
        }

        $object = (object) ['value' => null, 'items' => []];
        $constant = 'PSYSH_LIST_OBJECT_'.\uniqid();
        \define($constant, $object);

        $cleaner = new CodeCleaner();
        $code = '$class = new class { const OBJECT = \\'.$constant.'; };'.
            '[\\'.$constant.'->value, $class::OBJECT->items[0]] = [42, 23];';
        eval($cleaner->clean([$code]));

        $this->assertSame(42, $object->value);
        $this->assertSame([23], $object->items);
    }

    private function parseConstantPropertyStatement(string $code): array
    {
        try {
            return $this->parse($code);
        } catch (ParseErrorException $e) {
            if (\method_exists(FuncCall::class, 'isPartialFunctionApplication')) {
                throw $e;
            }

            $this->markTestSkipped('This PHP-Parser version does not support constant property access.');
        }
    }
}
