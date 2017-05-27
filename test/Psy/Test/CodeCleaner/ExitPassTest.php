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

use Psy\CodeCleaner\ExitPass;

class ExitPassTest extends CodeCleanerTestCase
{
    /**
     * @var string
     */
    private $expectedExceptionString = '\\Psy\\Exception\\BreakException::exitShell()';

    public function setUp()
    {
        $this->setPass(new ExitPass());
    }

    /**
     * @dataProvider dataProviderExitStatement
     */
    public function testExitStatement($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    /**
     * Data provider for testExitStatement.
     *
     * @return array
     */
    public function dataProviderExitStatement()
    {
        return array(
            array('exit;', "{$this->expectedExceptionString};"),
            array('exit();', "{$this->expectedExceptionString};"),
            array('die;', "{$this->expectedExceptionString};"),
            array('exit(die(die));', "{$this->expectedExceptionString};"),
            array('if (true) { exit; }', "if (true) {\n    {$this->expectedExceptionString};\n}"),
            array('if (false) { exit; }', "if (false) {\n    {$this->expectedExceptionString};\n}"),
            array('1 and exit();', "1 and {$this->expectedExceptionString};"),
            array('foo() or die', "foo() or {$this->expectedExceptionString};"),
            array('exit and 1;', "{$this->expectedExceptionString} and 1;"),
            array('if (exit) { echo $wat; }', "if ({$this->expectedExceptionString}) {\n    echo \$wat;\n}"),
            array('exit or die;', "{$this->expectedExceptionString} or {$this->expectedExceptionString};"),
            array('switch (die) { }', "switch ({$this->expectedExceptionString}) {\n}"),
            array('for ($i = 1; $i < 10; die) {}', "for (\$i = 1; \$i < 10; {$this->expectedExceptionString}) {\n}"),
        );
    }
}
