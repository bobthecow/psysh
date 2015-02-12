<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ValidConstantPass;

class ValidConstantPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new ValidConstantPass());
    }

    /**
     * @dataProvider getInvalidReferences
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalidConstantReferences($code)
    {
        $stmts = $this->parse($code);
        $this->traverse($stmts);
    }

    public function getInvalidReferences()
    {
        return array(
            array('Foo\BAR'),

            // class constant fetch
            array('Psy\Test\CodeCleaner\ValidConstantPassTest::FOO'),
            array('DateTime::BACON'),
        );
    }

    /**
     * @dataProvider getValidReferences
     */
    public function testProcessValidConstantReferences($code)
    {
        $stmts = $this->parse($code);
        $this->traverse($stmts);
    }

    public function getValidReferences()
    {
        return array(
            array('PHP_EOL'),

            // class constant fetch
            array('NotAClass::FOO'),
            array('DateTime::ATOM'),
            array('$a = new DateTime; $a::ATOM'),
        );
    }
}
