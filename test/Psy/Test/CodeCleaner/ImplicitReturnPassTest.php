<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ImplicitReturnPass;

class ImplicitReturnPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass = new ImplicitReturnPass;
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
        return array(
            array('4',     'return 4;'),
            array('foo()', 'return foo();'),
        );
     }
}
