<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\ShellLogger;
use Psy\Test\Util\FakePsrLogger;

class ShellLoggerTest extends TestCase
{
    public function testLogInput()
    {
        $psrLogger = new FakePsrLogger();
        $logger = new ShellLogger($psrLogger, [
            'input'   => 'info',
            'command' => 'info',
            'execute' => 'debug',
        ]);

        $logger->logInput('$foo = 123');

        $this->assertCount(1, $psrLogger->logs);
        $this->assertSame('info', $psrLogger->logs[0]['level']);
        $this->assertSame('PsySH input', $psrLogger->logs[0]['message']);
        $this->assertSame(['input' => '$foo = 123'], $psrLogger->logs[0]['context']);
    }

    public function testLogCommand()
    {
        $psrLogger = new FakePsrLogger();
        $logger = new ShellLogger($psrLogger, [
            'input'   => 'info',
            'command' => 'info',
            'execute' => 'debug',
        ]);

        $logger->logCommand('doc array_map');

        $this->assertCount(1, $psrLogger->logs);
        $this->assertSame('info', $psrLogger->logs[0]['level']);
        $this->assertSame('PsySH command', $psrLogger->logs[0]['message']);
        $this->assertSame(['command' => 'doc array_map'], $psrLogger->logs[0]['context']);
    }

    public function testLogExecute()
    {
        $psrLogger = new FakePsrLogger();
        $logger = new ShellLogger($psrLogger, [
            'input'   => 'info',
            'command' => 'info',
            'execute' => 'debug',
        ]);

        $logger->logExecute('return $foo + 1;');

        $this->assertCount(1, $psrLogger->logs);
        $this->assertSame('debug', $psrLogger->logs[0]['level']);
        $this->assertSame('PsySH execute', $psrLogger->logs[0]['message']);
        $this->assertSame(['code' => 'return $foo + 1;'], $psrLogger->logs[0]['context']);
    }

    public function testDisabledInput()
    {
        $psrLogger = new FakePsrLogger();
        $logger = new ShellLogger($psrLogger, [
            'input'   => false,
            'command' => 'info',
            'execute' => 'debug',
        ]);

        $logger->logInput('$foo = 123');

        $this->assertCount(0, $psrLogger->logs);
    }

    public function testDisabledCommand()
    {
        $psrLogger = new FakePsrLogger();
        $logger = new ShellLogger($psrLogger, [
            'input'   => 'info',
            'command' => false,
            'execute' => 'debug',
        ]);

        $logger->logCommand('doc array_map');

        $this->assertCount(0, $psrLogger->logs);
    }

    public function testDisabledExecute()
    {
        $psrLogger = new FakePsrLogger();
        $logger = new ShellLogger($psrLogger, [
            'input'   => 'info',
            'command' => 'info',
            'execute' => false,
        ]);

        $logger->logExecute('return $foo + 1;');

        $this->assertCount(0, $psrLogger->logs);
    }

    public function testIsInputDisabled()
    {
        $psrLogger = new FakePsrLogger();

        $logger = new ShellLogger($psrLogger, ['input' => false, 'command' => 'info', 'execute' => 'debug']);
        $this->assertTrue($logger->isInputDisabled());

        $logger = new ShellLogger($psrLogger, ['input' => 'info', 'command' => 'info', 'execute' => 'debug']);
        $this->assertFalse($logger->isInputDisabled());
    }

    public function testIsCommandDisabled()
    {
        $psrLogger = new FakePsrLogger();

        $logger = new ShellLogger($psrLogger, ['input' => 'info', 'command' => false, 'execute' => 'debug']);
        $this->assertTrue($logger->isCommandDisabled());

        $logger = new ShellLogger($psrLogger, ['input' => 'info', 'command' => 'info', 'execute' => 'debug']);
        $this->assertFalse($logger->isCommandDisabled());
    }

    public function testIsExecuteDisabled()
    {
        $psrLogger = new FakePsrLogger();

        $logger = new ShellLogger($psrLogger, ['input' => 'info', 'command' => 'info', 'execute' => false]);
        $this->assertTrue($logger->isExecuteDisabled());

        $logger = new ShellLogger($psrLogger, ['input' => 'info', 'command' => 'info', 'execute' => 'debug']);
        $this->assertFalse($logger->isExecuteDisabled());
    }
}
