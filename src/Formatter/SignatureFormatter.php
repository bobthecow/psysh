<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

/**
 * An abstract representation of a function, class or property signature.
 *
 * @deprecated use Psy\Formatter\formatSignature() directly
 */
class SignatureFormatter implements Formatter
{
    /**
     * Format a signature for the given reflector.
     *
     * Defers to subclasses to do the actual formatting.
     *
     * @deprecated use Psy\Formatter\formatSignature() directly
     *
     * @param \Reflector $reflector
     *
     * @return string Formatted signature
     */
    public static function format(\Reflector $reflector)
    {
        return formatSignature($reflector);
    }

    /**
     * Print the signature name.
     *
     * @deprecated use $reflector->getName() instead
     *
     * @param \Reflector $reflector
     *
     * @return string Formatted name
     */
    public static function formatName(\Reflector $reflector)
    {
        return $reflector->getName();
    }
}
