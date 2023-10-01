<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ListPass;
use Psy\Exception\ParseErrorException;

/**
 * @group isolation-fail
 */
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
}
