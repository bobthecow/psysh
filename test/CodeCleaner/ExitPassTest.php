<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ExitPass;

/**
 * @group isolation-fail
 */
class ExitPassTest extends CodeCleanerTestCase
{
    /**
     * @var string
     */
    private $expectedExceptionString = '\\Psy\\Exception\\BreakException::exitShell(%s)';

    /**
     * @before
     */
    public function getReady()
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
     * Helper to generate exit call with optional arguments.
     *
     * @param string $args Arguments to pass to exitShell
     *
     * @return string
     */
    protected function exitCall($args = '')
    {
        return \sprintf($this->expectedExceptionString, $args);
    }

    /**
     * Data provider for testExitStatement.
     *
     * @return array
     */
    public function dataProviderExitStatement()
    {
        return [
            ['exit;', "{$this->exitCall()};"],
            ['exit();', "{$this->exitCall()};"],
            ['die;', "{$this->exitCall()};"],
            ['exit(die(die));', "{$this->exitCall($this->exitCall($this->exitCall()))};"],
            ['if (true) { exit; }', "if (true) {\n    {$this->exitCall()};\n}"],
            ['if (false) { exit; }', "if (false) {\n    {$this->exitCall()};\n}"],
            ['1 and exit();', "1 and {$this->exitCall()};"],
            ['foo() or die', "foo() or {$this->exitCall()};"],
            ['exit and 1;', "{$this->exitCall()} and 1;"],
            ['if (exit) { echo $wat; }', "if ({$this->exitCall()}) {\n    echo \$wat;\n}"],
            ['exit or die;', "{$this->exitCall()} or {$this->exitCall()};"],
            ['switch (die) { }', "switch ({$this->exitCall()}) {\n}"],
            ['for ($i = 1; $i < 10; die) {}', "for (\$i = 1; \$i < 10; {$this->exitCall()}) {\n}"],
            ['exit(1);', "{$this->exitCall('1')};"],
            ['die(42);', "{$this->exitCall('42')};"],
            ['exit($x = 1);', "{$this->exitCall('$x = 1')};"],
        ];
    }
}
