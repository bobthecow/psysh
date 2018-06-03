<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use Psy\Command\ThrowUpCommand;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

class ThrowUpCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider executeThis
     */
    public function testExecute($args, $hasCode, $expect)
    {
        $shell = $this->getMockBuilder('Psy\\Shell')
            ->setMethods(['hasCode', 'addCode'])
            ->getMock();

        $shell->expects($this->once())->method('hasCode')->willReturn($hasCode);
        $shell->expects($this->once())
            ->method('addCode')
            ->with($this->equalTo($expect), $this->equalTo(!$hasCode));

        $command = new ThrowUpCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);
        $tester->execute($args);
        $this->assertEquals('', $tester->getDisplay());
    }

    public function executeThis()
    {
        $throw = 'throw \Psy\Exception\ThrowUpException::fromThrowable';

        return [
            [[], false, $throw . '($_e);'],
            [[], true, $throw . '($_e);'],

            [['exception' => '$ex'], true, $throw . '($ex);'],
            [['exception' => 'getException()'], true, $throw . '(getException());'],
            [['exception' => 'new \\Exception("WAT")'], true, $throw . '(new \\Exception("WAT"));'],

            [['exception' => '\'some string\''], true, $throw . '(new \\Exception(\'some string\'));'],
            [['exception' => '"WHEEEEEEE!"'], true, $throw . '(new \\Exception("WHEEEEEEE!"));'],
        ];
    }
}
