<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ImplicitReturnPass;

class ImplicitReturnPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new ImplicitReturnPass());
    }

    /**
     * @dataProvider implicitReturns
     */
    public function testProcess($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function implicitReturns()
    {
        $values = array(
            array('4',     'return 4;'),
            array('foo()', 'return foo();'),
        );

        if (version_compare(PHP_VERSION, '5.4', '<')) {
            $values[] = array('exit()', 'die;');
        } else {
            $values[] = array('exit()', 'exit;');
        }

        return $values;
    }
}
