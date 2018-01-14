<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
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
        $this->assertSame(['Alpha'], $this->cleaner->getNamespace());

        // A new non-block namespace statement should override the current namespace.
        $this->process('namespace Beta; class B {}');
        $this->assertSame(['Beta'], $this->cleaner->getNamespace());

        // A new block namespace clears out the current namespace...
        $this->process('namespace Gamma { array_merge(); }');

        if (defined('PhpParser\\Node\\Stmt\\Namespace_::KIND_SEMICOLON')) {
            $this->assertNull($this->cleaner->getNamespace());
        } else {
            // But not for PHP-Parser < v3.1.2 :(
            $this->assertSame(['Gamma'], $this->cleaner->getNamespace());
        }

        $this->process('namespace Delta');

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
