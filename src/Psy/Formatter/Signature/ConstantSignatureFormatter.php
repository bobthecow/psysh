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
            '<info>const</info> <strong>%s</strong> = <return>%s</return>',
            self::formatName($reflector),
            $reflector->getValue()
        );
    }
}
