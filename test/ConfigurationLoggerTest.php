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

use Psy\Configuration;
use Psy\ExecutionLoop\ExecutionLoggingListener;
use Psy\ExecutionLoop\InputLoggingListener;
use Psy\Logger\CallbackLogger;
use Psy\ShellLogger;
use Psy\Test\Util\FakePsrLogger;

class ConfigurationLoggerTest extends TestCase
{
    public function testNoLoggerByDefault()
    {
        $config = new Configuration();

        $this->assertNull($config->getLogger());
        $this->assertNull($config->getInputLogger());
        $this->assertNull($config->getExecutionLogger());
    }

    public function testSetLogging()
    {
        $config = new Configuration();
        $psrLogger = new FakePsrLogger();

        $config->setLogging($psrLogger);

        $logger = $config->getLogger();
        $this->assertInstanceOf(ShellLogger::class, $logger);
    }

    public function testGetLoggerWithSimpleConfig()
    {
        $config = new Configuration();
        $psrLogger = new FakePsrLogger();

        $config->setLogging($psrLogger);

        $logger = $config->getLogger();

        $this->assertInstanceOf(ShellLogger::class, $logger);
        $this->assertFalse($logger->isInputDisabled());
        $this->assertFalse($logger->isCommandDisabled());
        $this->assertFalse($logger->isExecuteDisabled());
    }

    public function testGetLoggerWithArrayConfig()
    {
        $config = new Configuration();
        $psrLogger = new FakePsrLogger();

        $config->setLogging([
            'logger' => $psrLogger,
            'level'  => 'warning',
        ]);

        $logger = $config->getLogger();

        $this->assertInstanceOf(ShellLogger::class, $logger);

        // Verify it uses the configured level by logging something
        $logger->logInput('test');
        $this->assertSame('warning', $psrLogger->logs[0]['level']);
    }

    public function testGetLoggerWithGranularLevels()
    {
        $config = new Configuration();
        $psrLogger = new FakePsrLogger();

        $config->setLogging([
            'logger' => $psrLogger,
            'level'  => [
                'input'   => 'debug',
                'command' => 'info',
                'execute' => false,
            ],
        ]);

        $logger = $config->getLogger();

        $this->assertInstanceOf(ShellLogger::class, $logger);
        $this->assertFalse($logger->isInputDisabled());
        $this->assertFalse($logger->isCommandDisabled());
        $this->assertTrue($logger->isExecuteDisabled());

        // Verify levels
        $logger->logInput('test');
        $this->assertSame('debug', $psrLogger->logs[0]['level']);

        $logger->logCommand('test');
        $this->assertSame('info', $psrLogger->logs[1]['level']);
    }

    public function testGetInputLoggerReturnsListenerWhenEnabled()
    {
        $config = new Configuration();
        $psrLogger = new FakePsrLogger();

        $config->setLogging($psrLogger);

        $listener = $config->getInputLogger();

        $this->assertInstanceOf(InputLoggingListener::class, $listener);
    }

    public function testGetInputLoggerReturnsNullWhenDisabled()
    {
        $config = new Configuration();
        $psrLogger = new FakePsrLogger();

        $config->setLogging([
            'logger' => $psrLogger,
            'level'  => [
                'input' => false,
            ],
        ]);

        $listener = $config->getInputLogger();

        $this->assertNull($listener);
    }

    public function testGetExecutionLoggerReturnsListenerWhenEnabled()
    {
        $config = new Configuration();
        $psrLogger = new FakePsrLogger();

        $config->setLogging($psrLogger);

        $listener = $config->getExecutionLogger();

        $this->assertInstanceOf(ExecutionLoggingListener::class, $listener);
    }

    public function testGetExecutionLoggerReturnsNullWhenDisabled()
    {
        $config = new Configuration();
        $psrLogger = new FakePsrLogger();

        $config->setLogging([
            'logger' => $psrLogger,
            'level'  => [
                'execute' => false,
            ],
        ]);

        $listener = $config->getExecutionLogger();

        $this->assertNull($listener);
    }

    public function testSetLoggingWithClosure()
    {
        $config = new Configuration();
        $logged = [];

        // Simple closure - no need to create CallbackLogger manually
        $config->setLogging(function ($kind, $data) use (&$logged) {
            $logged[] = ['kind' => $kind, 'data' => $data];
        });

        $logger = $config->getLogger();
        $this->assertInstanceOf(ShellLogger::class, $logger);

        // Verify it works
        $logger->logInput('test input');
        $this->assertCount(1, $logged);
        $this->assertSame('input', $logged[0]['kind']);
        $this->assertSame('test input', $logged[0]['data']);
    }

    public function testSetLoggingWithCallbackLogger()
    {
        $config = new Configuration();
        $logged = [];

        // Users CAN create CallbackLogger explicitly if they want
        $callbackLogger = new CallbackLogger(function ($kind, $data) use (&$logged) {
            $logged[] = ['kind' => $kind, 'data' => $data];
        });

        $config->setLogging($callbackLogger);

        $logger = $config->getLogger();
        $this->assertInstanceOf(ShellLogger::class, $logger);

        // Verify it works
        $logger->logInput('test input');
        $this->assertCount(1, $logged);
        $this->assertSame('input', $logged[0]['kind']);
        $this->assertSame('test input', $logged[0]['data']);
    }

    public function testSetLoggingWithCallbackLoggerAndLevels()
    {
        $config = new Configuration();
        $logged = [];

        $callbackLogger = new CallbackLogger(function ($kind, $data) use (&$logged) {
            $logged[] = ['kind' => $kind, 'data' => $data];
        });

        $config->setLogging([
            'logger' => $callbackLogger,
            'level'  => [
                'input'   => 'debug',
                'execute' => false,
            ],
        ]);

        $logger = $config->getLogger();
        $this->assertFalse($logger->isInputDisabled());
        $this->assertTrue($logger->isExecuteDisabled());
    }
}
