<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ValidConstantPass;

class ValidConstantPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new ValidConstantPass);
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
            array('Foo\BAR;'),
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
            array('PHP_EOL;')
        );
    }
}
