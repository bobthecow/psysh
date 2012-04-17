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

use Psy\Formatter\Signature\SignatureFormatter;

/**
 * Property signature representation.
 */
class FunctionSignatureFormatter extends SignatureFormatter
{
    /**
     * {@inheritdoc}
     */
    public static function format(\Reflector $reflector)
    {
        return sprintf(
            '<info>function</info> %s<strong>%s</strong>(%s)',
            self::formatReturnsReference($reflector),
            self::formatName($reflector),
            implode(', ', self::formatParams($reflector))
        );
    }

    /**
     * Print an `&` if this function returns by reference.
     *
     * @return string
     */
    protected static function formatReturnsReference(\Reflector $reflector)
    {
        if ($reflector->returnsReference()) {
            return '&';
        }
    }

    /**
     * Print the function params.
     *
     * @return string
     */
    protected static function formatParams(\Reflector $reflector)
    {
        $params = array();
        foreach ($reflector->getParameters() as $param) {
            if ($param->isArray()) {
                $hint = '<info>array</info> ';
            } elseif ($class = $param->getClass()) {
                $hint = sprintf('<info>%s</info> ', $class->getName());
            } else {
                $hint = '';
            }

            if ($param->isOptional()) {
                if (!$param->isDefaultValueAvailable()) {
                    $value = 'unknown';
                } else {
                    $value = $param->getDefaultValue();
                    $value = is_array($value) ? 'array()' : is_null($value) ? 'null' : var_export($value, true);
                }
                $default = sprintf(' = <return>%s</return>', $value);
            } else {
                $default = '';
            }

            $params[] = sprintf(
                '%s%s<strong>$%s</strong>%s',
                $param->isPassedByReference() ? '&' : '',
                $hint,
                $param->getName(),
                $default
            );
        }

        return $params;
    }
}
