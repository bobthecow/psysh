<?php

$config->setTempDir(sys_get_temp_dir().'/phpsh_test/withconfig/temp');

return array(
    'useReadline' => true,
    'usePcntl'    => false,
);
