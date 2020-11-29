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

use Psy\CodeCleaner\EmptyArrayDimFetchPass;

class EmptyArrayDimFetchPassTest extends CodeCleanerTestCase
{
    protected function setUp()
    {
        $this->setPass(new EmptyArrayDimFetchPass());
    }

    /**
     * @dataProvider invalidStatements
     */
    public function testProcessInvalidStatement($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->parseAndTraverse($code);
    }

    public function invalidStatements()
    {
        return [
            ['$foo[]'],
            ['echo $foo[]'],
            ['${$foo}[]'],
            ['array_pop($this->foo[])'],
            ['$foo[] = $bar[]'],
        ];
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessValidStatement($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        $data = [
            ['$foo[] = "bar"'],
            ['$this->foo[] = 1'],
            ['$foo->{$bar}[] = 1'],
        ];

        return $data;
    }
}
