<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\PassableByReferencePass;

class PassableByReferencePassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new PassableByReferencePass());
    }

    /**
     * @dataProvider invalidStatements
     */
    public function testProcessStatementFails($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function invalidStatements()
    {
        return [
            ['array_pop([])'],
            ['array_pop([$foo])'],
            ['array_shift([])'],
        ];
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessStatementPasses($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        $stmts = [
            ['array_pop(json_decode("[]"))'],
            ['array_pop($foo)'],
            ['array_pop($foo->bar)'],
            ['array_pop($foo::baz)'],
            ['array_pop(Foo::qux)'],
            ['array_pop($foo["quux"])'],
        ];

        if (\version_compare(\PHP_VERSION, '5.6', '>=')) {
            $stmts[] = ['end(...[$a])'];
        }

        return $stmts;
    }

    /**
     * @dataProvider validArrayMultisort
     */
    public function testArrayMultisort($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function validArrayMultisort()
    {
        return [
            ['array_multisort($a)'],
            ['array_multisort($a, $b)'],
            ['array_multisort($a, SORT_NATURAL, $b)'],
            ['array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, $b)'],
            ['array_multisort($a, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE, $b)'],
            ['array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, SORT_ASC, $b)'],
            ['array_multisort($a, $b, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE)'],
            ['array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, $b, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE)'],
            ['array_multisort($a, 1, $b)'],
            ['array_multisort($a, 1 + 2, $b)'],
            ['array_multisort($a, getMultisortFlags(), $b)'],
        ];
    }

    /**
     * @dataProvider invalidArrayMultisort
     */
    public function testInvalidArrayMultisort($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function invalidArrayMultisort()
    {
        return [
            ['array_multisort(1)'],
            ['array_multisort([1, 2, 3])'],
            ['array_multisort($a, SORT_NATURAL, SORT_ASC, SORT_NATURAL, $b)'],
        ];
    }
}
