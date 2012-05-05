<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!class_exists('Symfony\Component\ClassLoader\UniversalClassLoader')) {
    require dirname(__DIR__).'/vendor/symfony-components/Symfony/Component/ClassLoader/UniversalClassLoader.php';
}

$loader = new \Symfony\Component\ClassLoader\UniversalClassLoader();
$loader->register();

$loader->registerNamespaces(array(
    'Psy'     => __DIR__,
    'Symfony' => dirname(__DIR__).'/vendor/symfony-components',
));

$loader->registerPrefixes(array(
    'PHPParser_' => dirname(__DIR__).'/vendor/php-parser/lib',
));
