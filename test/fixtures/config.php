<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$config->setRuntimeDir(sys_get_temp_dir() . '/psysh_test/withconfig/temp');

return array(
    'useReadline'       => true,
    'usePcntl'          => false,
    'errorLoggingLevel' => E_ALL & ~E_NOTICE,
);
