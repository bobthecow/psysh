<?php

use Symfony\CS\Config\Config;
use Symfony\CS\FixerInterface;

$config = Config::create()
    // use symfony level and extra fixers:
    ->level(Symfony\CS\FixerInterface::SYMFONY_LEVEL)
    ->fixers(array('-concat_without_spaces', 'concat_with_spaces', 'strict'))
    ->setUsingLinter(false);

$finder = $config->getFinder()
    ->in('src')
    ->in('test');

return $config;
