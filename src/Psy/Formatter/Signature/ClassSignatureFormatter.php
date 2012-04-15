<?php

namespace Psy\Formatter\Signature;

use Psy\Formatter\Signature\SignatureFormatter;

/**
 * Class signature representation.
 */
class ClassSignatureFormatter extends SignatureFormatter
{
    /**
     * {@inheritdoc}
     */
    public static function format(\Reflector $reflector)
    {
        $chunks = array();

        if ($modifiers = self::formatModifiers($reflector)) {
            $chunks[] = $modifiers;
        }

        $chunks[] = $reflector->isInterface() ? 'interface' : 'class';
        $chunks[] = sprintf('<info>%s</info>', self::formatName($reflector));

        if ($parent = $reflector->getParentClass()) {
            $chunks[] = 'extends';
            $chunks[] = sprintf('<info>%s</info>', $parent->getName());
        }

        $interfaces = $reflector->getInterfaceNames();
        if (!empty($interfaces)) {
            $chunks[] = 'implements';
            $chunks[] = implode(', ', array_map(function($name) {
                return sprintf('<info>%s</info>', $name);
            }, $interfaces));
        }

        return implode(' ', $chunks);
    }
}
