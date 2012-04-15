<?php

namespace Psy\Formatter\Signature;

use Psy\Formatter\Signature\FunctionSignature;

/**
 * Class method signature representation.
 */
class MethodSignatureFormatter extends FunctionSignatureFormatter
{
    /**
     * {@inheritdoc}
     */
    public static function format(\Reflector $reflector)
    {
        return sprintf(
            '<info>%s</info> %s',
            self::formatModifiers($reflector),
            parent::format($reflector)
        );
    }
}
