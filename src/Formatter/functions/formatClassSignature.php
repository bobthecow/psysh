<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

if (!\function_exists('Psy\\Formatter\\formatClassSignature')) {
    /**
     * Format a class signature.
     *
     * @param \ReflectionClass $reflector
     *
     * @return string Formatted signature
     */
    function formatClassSignature(\ReflectionClass $reflector)
    {
        $chunks = [];

        // For some reason, PHP 5.x returns `abstract public` modifiers for
        // traits. Let's just ignore that business entirely.
        if (\version_compare(PHP_VERSION, '7.0.0', '>=') || !$reflector->isTrait()) {
            $modifiers = \implode(' ', \array_map(function ($modifier) {
                return \sprintf('<keyword>%s</keyword>', $modifier);
            }, \Reflection::getModifierNames($reflector->getModifiers())));

            if ($modifiers !== '') {
                $chunks[] = $modifiers;
            }
        }

        if ($reflector->isTrait()) {
            $chunks[] = 'trait';
        } else {
            $chunks[] = $reflector->isInterface() ? 'interface' : 'class';
        }

        $chunks[] = \sprintf('<class>%s</class>', $reflector->getName());

        if ($parent = $reflector->getParentClass()) {
            $chunks[] = 'extends';
            $chunks[] = \sprintf('<class>%s</class>', $parent->getName());
        }

        $interfaces = $reflector->getInterfaceNames();
        if (!empty($interfaces)) {
            \sort($interfaces);

            $chunks[] = $reflector->isInterface() ? 'extends' : 'implements';
            $chunks[] = \implode(', ', \array_map(function ($name) {
                return \sprintf('<class>%s</class>', $name);
            }, $interfaces));
        }

        return \implode(' ', $chunks);
    }
}
