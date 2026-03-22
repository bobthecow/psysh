<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Psy\Shell;

require_once __DIR__.'/TempPaths.php';
require_once __DIR__.'/bootstrap-env.php';
Psy\Test\BootstrapEnv::isolate(\dirname(__DIR__).'/.test-env');

// `make test-phar` copies the built PHAR next to the copied test tree.
$pharPath = \dirname(__DIR__).'/psysh';

if (!\is_file($pharPath)) {
    throw new RuntimeException('Could not find PsySH PHAR bootstrap.');
}

require_once $pharPath;

// PHPUnit only sees the copied `test/` tree, so wire `Psy\Test\...` classes
// directly to files under this directory.
\spl_autoload_register(static function (string $class): void {
    $prefix = 'Psy\\Test\\';
    if (\strncmp($class, $prefix, \strlen($prefix)) !== 0) {
        return;
    }

    $path = __DIR__.\DIRECTORY_SEPARATOR.\str_replace('\\', \DIRECTORY_SEPARATOR, \substr($class, \strlen($prefix))).'.php';
    if (\is_file($path)) {
        require_once $path;
    }
});

// A few list-command tests rely on these fixtures being declared up front so
// they appear in reflection-based enumeration.
$fixtureDir = __DIR__.'/Fixtures/Command/ListCommand';
foreach ([
    $fixtureDir.'/functions.php',
    $fixtureDir.'/ClassAlfa.php',
    $fixtureDir.'/ClassBravo.php',
    $fixtureDir.'/ClassCharlie.php',
    $fixtureDir.'/InterfaceDelta.php',
    $fixtureDir.'/InterfaceEcho.php',
    $fixtureDir.'/TraitFoxtrot.php',
    $fixtureDir.'/TraitGolf.php',
] as $fixtureFile) {
    if (\is_file($fixtureFile)) {
        require_once $fixtureFile;
    }
}

// Scoped PHAR builds rename vendor classes to `_Psy<hash>\...`; infer the
// current prefix from Shell's Symfony parent class instead of hardcoding it.
$parent = (new ReflectionClass(Shell::class))->getParentClass();
if ($parent === false) {
    throw new RuntimeException('Could not determine PsySH PHAR prefix.');
}

if (!\preg_match('/^(_Psy[a-f0-9]+)\\\\Symfony\\\\Component\\\\Console\\\\Application$/', $parent->getName(), $matches)) {
    throw new RuntimeException('Could not determine PsySH PHAR prefix.');
}

$scopedPrefix = $matches[1];
// Only alias namespaces that our tests reference directly.
$aliasableNamespaces = [
    'Symfony\\Component\\Console\\',
    'Symfony\\Component\\Finder\\',
    'Symfony\\Component\\String\\',
    'Symfony\\Component\\VarDumper\\',
    'Symfony\\Contracts\\Service\\',
    'Psr\\Container\\',
    'PhpParser\\',
];

$shouldAlias = static function (string $class) use ($aliasableNamespaces): bool {
    foreach ($aliasableNamespaces as $namespace) {
        if (\strncmp($class, $namespace, \strlen($namespace)) === 0) {
            return true;
        }
    }

    return false;
};

$ensureAlias = static function (string $class) use ($shouldAlias, $scopedPrefix): void {
    if (!$shouldAlias($class)) {
        return;
    }

    if (\class_exists($class, false) || \interface_exists($class, false) || \trait_exists($class, false)) {
        return;
    }

    $scopedClass = $scopedPrefix.'\\'.$class;
    try {
        if (!\class_exists($scopedClass, true) && !\interface_exists($scopedClass, true) && !\trait_exists($scopedClass, true)) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }

    \class_alias($scopedClass, $class);
};

// Preload aliases for classes referenced in catch blocks and type declarations,
// where PHP will not necessarily trigger autoload before resolving the symbol.
foreach (\spl_autoload_functions() as $autoload) {
    if (!\is_array($autoload) || !\is_object($autoload[0] ?? null) || !\method_exists($autoload[0], 'getClassMap')) {
        continue;
    }

    foreach ($autoload[0]->getClassMap() as $class => $_path) {
        if (\strncmp($class, $scopedPrefix.'\\', \strlen($scopedPrefix) + 1) !== 0) {
            continue;
        }

        $ensureAlias(\substr($class, \strlen($scopedPrefix) + 1));
    }
}

// Keep a lazy aliaser around for any remaining test-only references.
\spl_autoload_register(static function (string $class) use ($ensureAlias): void {
    $ensureAlias($class);
}, true, true);

// Symfony's dump helpers become scoped inside the PHAR too, but tests refer to
// the global names.
$functionAliases = [
    'dump' => $scopedPrefix.'\\dump',
    'dd'   => $scopedPrefix.'\\dd',
];

foreach ($functionAliases as $alias => $target) {
    if (\function_exists($alias) || !\function_exists($target)) {
        continue;
    }

    eval(\sprintf(
        'function %s(...$args) { return \\call_user_func_array(%s, $args); }',
        $alias,
        \var_export('\\'.$target, true)
    ));
}
