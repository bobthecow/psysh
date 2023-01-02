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

use Psy\CodeCleaner\FinalClassPass;

class FinalClassPassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new FinalClassPass());
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
            ['final class A {} class B extends A {}'],
            ['class A {} final class B extends A {} class C extends B {}'],
            ['class A extends \\Closure {}'],
            // ['namespace A { final class B {} } namespace C { class D extends \\A\\B {} }'],
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
            ['class A extends \\stdClass {}'],
            ['final class A extends \\stdClass {}'],
            ['class A {} class B extends A {}'],
        ];
    }
}
