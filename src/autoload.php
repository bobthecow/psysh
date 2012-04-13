<?php

require dirname(__DIR__).'/vendor/symfony-components/Symfony/Component/ClassLoader/UniversalClassLoader.php';

$loader = new \Symfony\Component\ClassLoader\UniversalClassLoader();
$loader->register();

$loader->registerNamespaces(array(
    'Psy'     => __DIR__,
    'Symfony' => dirname(__DIR__).'/vendor/symfony-components',
//    'Doctrine' => dirname(__DIR__).'/vendor/doctrine-common/lib',
));

$loader->registerPrefixes(array(
    'PHPParser_' => dirname(__DIR__).'/vendor/php-parser/lib',
));
