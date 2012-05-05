<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter\Signature;

use Psy\Formatter\Signature\FunctionSignatureFormatter;

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
