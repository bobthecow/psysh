<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$config->setTempDir(sys_get_temp_dir().'/psysh_test/withconfig/temp');

return array(
    'useReadline' => true,
    'usePcntl'    => false,
);
