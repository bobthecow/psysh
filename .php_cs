<?php

use Symfony\CS\Config\Config;
use Symfony\CS\FixerInterface;

$config = Config::create()
    // use default level and extra fixers:
    ->fixers(array('-concat_without_spaces', 'concat_with_spaces', 'strict'))
    ->setUsingLinter(false);

$finder = $config->getFinder()
    ->in('src')
    ->in('test');

return $config;
