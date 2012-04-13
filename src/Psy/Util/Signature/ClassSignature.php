<?php

namespace Psy\Util\Signature;

use Psy\Util\Signature\Signature;

/**
 * Class signature representation.
 */
class ClassSignature extends Signature
{
    /**
     * {@inheritdoc}
     */
    public function prettyPrint()
    {
        $chunks = array();

        if ($modifiers = $this->printModifiers()) {
            $chunks[] = $modifiers;
        }

        $chunks[] = $this->reflector->isInterface() ? 'interface' : 'class';
        $chunks[] = sprintf('<info>%s</info>', $this->printName());

        if ($parent = $this->reflector->getParentClass()) {
            $chunks[] = 'extends';
            $chunks[] = sprintf('<info>%s</info>', $parent->getName());
        }

        $interfaces = $this->reflector->getInterfaceNames();
        if (!empty($interfaces)) {
            $chunks[] = 'implements';
            $chunks[] = implode(', ', array_map(function($name) {
                return sprintf('<info>%s</info>', $name);
            }, $interfaces));
        }

        return implode(' ', $chunks);
    }
}
