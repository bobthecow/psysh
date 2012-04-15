<?php

namespace Psy\Formatter\Signature;

use Psy\Formatter\Signature\SignatureFormatter;

/**
 * Class constant signature representation.
 */
class ConstantSignatureFormatter extends SignatureFormatter
{
    /**
     * {@inheritdoc}
     */
    public static function format(\Reflector $reflector)
    {
        return sprintf(
            'const <strong>%s</strong>',
            self::formatName($reflector)
        );
    }
}
