<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Logger;

use Psy\Logger\CallbackLogger;
use Psy\Test\TestCase;

class CallbackLoggerTest extends TestCase
{
    public function testLogInput()
    {
        $logged = [];
        $logger = new CallbackLogger(function ($kind, $data) use (&$logged) {
            $logged[] = ['kind' => $kind, 'data' => $data];
        });

        $logger->log('info', 'PsySH input', ['input' => '$foo = 123']);

        $this->assertCount(1, $logged);
        $this->assertSame('input', $logged[0]['kind']);
        $this->assertSame('$foo = 123', $logged[0]['data']);
    }

    public function testLogCommand()
    {
        $logged = [];
        $logger = new CallbackLogger(function ($kind, $data) use (&$logged) {
            $logged[] = ['kind' => $kind, 'data' => $data];
        });

        $logger->log('info', 'PsySH command', ['command' => 'ls']);

        $this->assertCount(1, $logged);
        $this->assertSame('command', $logged[0]['kind']);
        $this->assertSame('ls', $logged[0]['data']);
    }

    public function testLogExecute()
    {
        $logged = [];
        $logger = new CallbackLogger(function ($kind, $data) use (&$logged) {
            $logged[] = ['kind' => $kind, 'data' => $data];
        });

        $logger->log('debug', 'PsySH execute', ['code' => 'return 42;']);

        $this->assertCount(1, $logged);
        $this->assertSame('execute', $logged[0]['kind']);
        $this->assertSame('return 42;', $logged[0]['data']);
    }

    public function testMultipleLogs()
    {
        $logged = [];
        $logger = new CallbackLogger(function ($kind, $data) use (&$logged) {
            $logged[] = ['kind' => $kind, 'data' => $data];
        });

        $logger->log('info', 'PsySH input', ['input' => '$x = 1']);
        $logger->log('info', 'PsySH command', ['command' => 'doc array_map']);
        $logger->log('debug', 'PsySH execute', ['code' => 'return $x;']);

        $this->assertCount(3, $logged);
        $this->assertSame('input', $logged[0]['kind']);
        $this->assertSame('command', $logged[1]['kind']);
        $this->assertSame('execute', $logged[2]['kind']);
    }
}
