<?php

use Symfony\CS\Config\Config;
use Symfony\CS\Fixer;
use Symfony\CS\FixerInterface;

$config = new Config();

$config->fixers(array('-concat_without_spaces', 'concat_with_spaces'));

$config->getFinder()
	->in(__DIR__)
	->exclude('bin')
	->exclude('vendor');

return $config;
