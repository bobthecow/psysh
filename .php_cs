<?php

use Symfony\CS\Config\Config;
use Symfony\CS\FixerInterface;
use Symfony\CS\Fixer\Contrib\HeaderCommentFixer;

$header = <<<EOF
This file is part of Psy Shell.

(c) 2012-2015 Justin Hileman

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

HeaderCommentFixer::setHeader($header);

$config = Config::create()
    // use symfony level and extra fixers:
    ->level(FixerInterface::SYMFONY_LEVEL)
    ->fixers(array(
        'align_double_arrow',
        'concat_with_spaces',
        'header_comment',
        'long_array_syntax',
        'ordered_use',
        'strict',
        '-concat_without_spaces',
        '-method_argument_space',
        '-pre_increment',
        '-unalign_double_arrow',
        '-unalign_equals',
    ))
    ->setUsingLinter(false);

$finder = $config->getFinder()
    ->in(__DIR__)
    ->name('.php_cs')
    ->name('build-manual')
    ->name('build-phar')
    ->exclude('build-vendor');

return $config;
