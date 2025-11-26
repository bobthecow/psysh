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

use Psy\SystemEnv;

class SystemEnvTest extends TestCase
{
    public function testGetReturnsServerValueWhenSet()
    {
        $originalValue = $_SERVER['TEST_SYSTEM_ENV_VAR'] ?? null;

        try {
            $_SERVER['TEST_SYSTEM_ENV_VAR'] = 'server_value';
            $env = new SystemEnv();
            $this->assertSame('server_value', $env->get('TEST_SYSTEM_ENV_VAR'));
        } finally {
            if ($originalValue === null) {
                unset($_SERVER['TEST_SYSTEM_ENV_VAR']);
            } else {
                $_SERVER['TEST_SYSTEM_ENV_VAR'] = $originalValue;
            }
        }
    }

    public function testGetReturnsEnvValueWhenServerNotSet()
    {
        $key = 'TEST_SYSTEM_ENV_GETENV_'.\uniqid();

        // Make sure it's not in $_SERVER
        unset($_SERVER[$key]);

        \putenv("{$key}=env_value");
        try {
            $env = new SystemEnv();
            $this->assertSame('env_value', $env->get($key));
        } finally {
            \putenv($key);
        }
    }

    public function testGetReturnsNullWhenNotSet()
    {
        $key = 'TEST_SYSTEM_ENV_NOT_SET_'.\uniqid();

        unset($_SERVER[$key]);
        \putenv($key);

        $env = new SystemEnv();
        $this->assertNull($env->get($key));
    }

    public function testGetPrefersServerOverEnv()
    {
        $key = 'TEST_SYSTEM_ENV_PRIORITY_'.\uniqid();

        $_SERVER[$key] = 'server_value';
        \putenv("{$key}=env_value");

        try {
            $env = new SystemEnv();
            $this->assertSame('server_value', $env->get($key));
        } finally {
            unset($_SERVER[$key]);
            \putenv($key);
        }
    }

    public function testGetIgnoresEmptyServerValue()
    {
        $key = 'TEST_SYSTEM_ENV_EMPTY_'.\uniqid();

        $_SERVER[$key] = '';
        \putenv("{$key}=env_value");

        try {
            $env = new SystemEnv();
            $this->assertSame('env_value', $env->get($key));
        } finally {
            unset($_SERVER[$key]);
            \putenv($key);
        }
    }
}
