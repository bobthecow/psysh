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

use Psy\CodeCleaner\LegacyEmptyPass;

class LegacyEmptyPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new LegacyEmptyPass());
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\ParseErrorException
     */
    public function testProcessInvalidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        if (version_compare(PHP_VERSION, '5.5', '>=')) {
            return array(
                array('empty()'),
            );
        }

        return array(
            array('empty()'),
            array('empty(null)'),
            array('empty(PHP_EOL)'),
            array('empty("wat")'),
            array('empty(1.1)'),
            array('empty(Foo::$bar)'),
        );
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessValidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);

        // @todo a better thing to assert here?
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        if (version_compare(PHP_VERSION, '5.5', '<')) {
            return array(
                array('empty($foo)'),
            );
        }

        return array(
            array('empty($foo)'),
            array('empty(null)'),
            array('empty(PHP_EOL)'),
            array('empty("wat")'),
            array('empty(1.1)'),
            array('empty(Foo::$bar)'),
        );
    }
}
