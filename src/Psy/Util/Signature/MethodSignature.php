<?php

namespace Psy\Util\Signature;

use Psy\Util\Signature\FunctionSignature;

/**
 * Class method signature representation.
 */
class MethodSignature extends FunctionSignature
{
    /**
     * {@inheritdoc}
     */
    public function prettyPrint()
    {
        return sprintf(
            '<info>%s</info> %s',
            $this->printModifiers(),
            parent::prettyPrint()
        );
    }
}
