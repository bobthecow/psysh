<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\EnvInterface;

class TestableEnv implements EnvInterface
{
    private $env;

    public function __construct(array $env = [])
    {
        $this->env = $env;
    }

    public function get(string $name)
    {
        return isset($this->env[$name]) ? $this->env[$name] : null;
    }
}
