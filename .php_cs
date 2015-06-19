<?php

use Symfony\CS\Config\Config;
use Symfony\CS\FixerInterface;

$config = Config::create()
    // use symfony level and extra fixers:
    ->level(Symfony\CS\FixerInterface::SYMFONY_LEVEL)
    ->fixers(array(
        'align_double_arrow',
        'concat_with_spaces',
        'ordered_use',
        'strict',
        '-concat_without_spaces',
        '-pre_increment',
        '-unalign_double_arrow',
        '-unalign_equals',
    ))
    ->setUsingLinter(false);

$finder = $config->getFinder()
    ->in('src')
    ->in('test');

return $config;
