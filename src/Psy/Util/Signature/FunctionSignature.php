<?php

namespace Psy\Util\Signature;

use Psy\Util\Signature\Signature;

/**
 * Property signature representation.
 */
class FunctionSignature extends Signature
{
    /**
     * {@inheritdoc}
     */
    public function prettyPrint()
    {
        return sprintf(
            '<info>function</info> %s<strong>%s</strong>(%s)',
            $this->printReturnsReference(),
            $this->printName(),
            implode(', ', $this->printParams())
        );
    }

    /**
     * Print an `&` if this function returns by reference.
     *
     * @return string
     */
    protected function printReturnsReference()
    {
        if ($this->reflector->returnsReference()) {
            return '&';
        }
    }

    /**
     * Print the function params.
     *
     * @return string
     */
    protected function printParams()
    {
        return array_map(function($param) {
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

            return sprintf(
                '%s%s<strong>$%s</strong>%s',
                $param->isPassedByReference() ? '&' : '',
                $hint,
                $param->getName(),
                $default
            );
        }, $this->reflector->getParameters());
    }
}
