<?php

namespace Psy\Util\Signature;

use Psy\Util\Signature\Signature;

/**
 * Property signature representation.
 */
class PropertySignature extends Signature
{
    /**
     * {@inheritdoc}
     */
    public function prettyPrint()
    {
        return sprintf(
            '%s <strong>$%s</strong>',
            $this->printModifiers(),
            $this->reflector->getName()
        );
    }
}
