<?php

namespace Psy\Util\Signature;

use Psy\Util\Signature\Signature;

/**
 * Class constant signature representation.
 */
class ConstantSignature extends Signature
{
    /**
     * {@inheritdoc}
     */
    public function prettyPrint()
    {
        return sprintf(
            'const <strong>%s</strong>',
            $this->printName()
        );
    }
}
