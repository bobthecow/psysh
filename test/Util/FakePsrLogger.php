<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

// If PSR-3 isn't available, define a minimal interface for testing
if (!\interface_exists('Psr\Log\LoggerInterface')) {
    interface FakeLoggerInterface
    {
        public function emergency($message, array $context = []);

        public function alert($message, array $context = []);

        public function critical($message, array $context = []);

        public function error($message, array $context = []);

        public function warning($message, array $context = []);

        public function notice($message, array $context = []);

        public function info($message, array $context = []);

        public function debug($message, array $context = []);

        public function log($level, $message, array $context = []);
    }

    // Create an alias so Configuration::isLogger() can find it
    \class_alias(FakeLoggerInterface::class, 'Psr\Log\LoggerInterface');
}

/**
 * Fake PSR-3 logger for testing.
 *
 * Implements PSR-3 LoggerInterface without requiring psr/log dependency.
 */
class FakePsrLogger implements \Psr\Log\LoggerInterface
{
    public $logs = [];

    public function emergency($message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
