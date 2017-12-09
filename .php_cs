<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('.php_cs')
    ->name('build-manual')
    ->name('build-phar')
    ->exclude('build-vendor');

$header = <<<EOF
This file is part of Psy Shell.

(c) 2012-2017 Justin Hileman

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

return PhpCsFixer\Config::create()
    ->setRules(array(
        '@Symfony' => true,
        'concat_space' => array('spacing' => 'one'),
        'header_comment' => array('header' => $header),
        'array_syntax' => array('syntax' => 'long'),
        'ordered_imports' => true,
        'method_argument_space' => array('keep_multiple_spaces_after_comma' => true),
        'pre_increment' => false,
        'binary_operator_spaces' => array(
            'align_double_arrow' => true,
            'align_equals' => null,
        ),
    ))
    ->setFinder($finder);
