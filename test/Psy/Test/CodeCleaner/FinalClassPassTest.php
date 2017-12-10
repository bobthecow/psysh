<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PhpParser\NodeTraverser;
use Psy\CodeCleaner\FinalClassPass;

class FinalClassPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new FinalClassPass();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->pass);
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessStatementFails($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        $stmts = array(
            array('final class A {} class B extends A {}'),
            array('class A {} final class B extends A {} class C extends B {}'),
            // array('namespace A { final class B {} } namespace C { class D extends \\A\\B {} }'),
        );

        if (!defined('HHVM_VERSION')) {
            // For some reason Closure isn't final in HHVM?
            $stmts[] = array('class A extends \\Closure {}');
        }

        return $stmts;
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessStatementPasses($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);

        // @todo a better thing to assert here?
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return array(
            array('class A extends \\stdClass {}'),
            array('final class A extends \\stdClass {}'),
            array('class A {} class B extends A {}'),
        );
    }
}
