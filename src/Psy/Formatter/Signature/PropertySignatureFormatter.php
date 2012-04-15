<?php

namespace Psy\Formatter\Signature;

use Psy\Formatter\Signature\SignatureFormatter;

/**
 * Property signature representation.
 */
class PropertySignatureFormatter extends SignatureFormatter
{
    /**
     * {@inheritdoc}
     */
    public static function format(\Reflector $reflector)
    {
        return sprintf(
            '%s <strong>$%s</strong>',
            self::formatModifiers($reflector),
            $reflector->getName()
        );
    }
}
