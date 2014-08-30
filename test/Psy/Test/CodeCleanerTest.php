<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\CodeCleaner;

class CodeCleanerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider codeProvider
     */
    public function testAutomaticSemicolons(array $lines, $requireSemicolons, $expected)
    {
        $cc = new CodeCleaner();
        $this->assertEquals($expected, $cc->clean($lines, $requireSemicolons));
    }

    public function codeProvider()
    {
        return array(
            array(array('true'),  false, 'return true;'),
            array(array('true;'), false, 'return true;'),
            array(array('true;'), true,  'return true;'),
            array(array('true'),  true,  false),

            array(array('echo "foo";', 'true'), false, "echo 'foo';\nreturn true;"),
            array(array('echo "foo";', 'true'), true , false),
        );
    }
}
