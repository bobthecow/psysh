<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\CallTimePassByReferencePass;

/**
 * @group isolation-fail
 */
class CallTimePassByReferencePassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new CallTimePassByReferencePass());
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
            ['f(&$arg)'],
            ['$object->method($first, &$arg)'],
            ['$closure($first, &$arg, $last)'],
            ['A::b(&$arg)'],
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
        return [
            ['[&$var]'],
            ['$a = &$b'],
            ['f([&$b])'],
        ];
    }
}
