<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\MagicConstantsPass;

class MagicConstantsPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new MagicConstantsPass);
    }

    /**
     * @dataProvider magicConstants
     */
    public function testProcess($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function magicConstants()
    {
        return array(
            array('__DIR__;', 'getcwd();'),
            array('__FILE__;', "'';"),
            array('___FILE___;', "___FILE___;"),
        );
    }
}
