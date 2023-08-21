<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
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
    private $expectedExceptionString = '\\Psy\\Exception\\BreakException::exitShell()';

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
     * Data provider for testExitStatement.
     *
     * @return array
     */
    public function dataProviderExitStatement()
    {
        return [
            ['exit;', "{$this->expectedExceptionString};"],
            ['exit();', "{$this->expectedExceptionString};"],
            ['die;', "{$this->expectedExceptionString};"],
            ['exit(die(die));', "{$this->expectedExceptionString};"],
            ['if (true) { exit; }', "if (true) { {$this->expectedExceptionString}; }"],
            ['if (false) { exit; }', "if (false) { {$this->expectedExceptionString}; }"],
            ['1 and exit();', "1 and {$this->expectedExceptionString};"],
            ['foo() or die', "foo() or {$this->expectedExceptionString};"],
            ['exit and 1;', "{$this->expectedExceptionString} and 1;"],
            ['if (exit) { echo $wat; }', "if ({$this->expectedExceptionString}) { echo \$wat; }"],
            ['exit or die;', "{$this->expectedExceptionString} or {$this->expectedExceptionString};"],
            ['switch (die) { }', "switch ({$this->expectedExceptionString}) {}"],
            ['for ($i = 1; $i < 10; die) {}', "for (\$i = 1; \$i < 10; {$this->expectedExceptionString}) {}"],
        ];
    }
}
