<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    throw new RuntimeException('Install dependencies to run test suite.');
}

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->add('Psy\\Test\\', __DIR__);
