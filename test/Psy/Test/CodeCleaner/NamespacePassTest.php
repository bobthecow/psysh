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

use Psy\CodeCleaner;
use Psy\CodeCleaner\NamespacePass;

class NamespacePassTest extends CodeCleanerTestCase
{
    private $cleaner;

    public function setUp()
    {
        $this->cleaner = new CodeCleaner();
        $this->setPass(new NamespacePass($this->cleaner));
    }

    public function testProcess()
    {
        $this->process('array_merge()');
        $this->assertNull($this->cleaner->getNamespace());

        // A non-block namespace statement should set the current namespace.
        $this->process('namespace Alpha');
        $this->assertEquals(array('Alpha'), $this->cleaner->getNamespace());

        // A new non-block namespace statement should override the current namespace.
        $this->process('namespace Beta; class B {}');
        $this->assertEquals(array('Beta'), $this->cleaner->getNamespace());

        // @todo Figure out if we can detect when the last namespace block is
        // bracketed or unbracketed, because this should really clear the
        // namespace at the end...
        $this->process('namespace Gamma { array_merge(); }');
        $this->assertEquals(array('Gamma'), $this->cleaner->getNamespace());

        // A null namespace clears out the current namespace.
        $this->process('namespace { array_merge(); }');
        $this->assertNull($this->cleaner->getNamespace());
    }

    private function process($code)
    {
        $stmts = $this->parse($code);
        $this->traverse($stmts);
    }
}
