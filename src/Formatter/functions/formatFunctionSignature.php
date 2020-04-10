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

use Symfony\Component\Console\Formatter\OutputFormatter;

if (!\function_exists('Psy\\Formatter\\formatFunctionSignature')) {
    /**
     * Format a function signature.
     *
     * @param \ReflectionFunction $reflector
     *
     * @return string Formatted signature
     */
    function formatFunctionSignature(\ReflectionFunctionAbstract $reflector)
    {
        $params = [];
        foreach ($reflector->getParameters() as $param) {
            $hint = '';
            try {
                if ($param->isArray()) {
                    $hint = '<keyword>array</keyword> ';
                } elseif ($class = $param->getClass()) {
                    $hint = \sprintf('<class>%s</class> ', $class->getName());
                }
            } catch (\Exception $e) {
                // sometimes we just don't know...
                // bad class names, or autoloaded classes that haven't been loaded yet, or whathaveyou.
                // come to think of it, the only time I've seen this is with the intl extension.

                // Hax: we'll try to extract it :P

                // @codeCoverageIgnoreStart
                $chunks = \explode('$' . $param->getName(), (string) $param);
                $chunks = \explode(' ', \trim($chunks[0]));
                $guess  = \end($chunks);

                $hint = \sprintf('<urgent>%s</urgent> ', $guess);
                // @codeCoverageIgnoreEnd
            }

            if ($param->isOptional()) {
                if (!$param->isDefaultValueAvailable()) {
                    $value     = 'unknown';
                    $typeStyle = 'urgent';
                } else {
                    $value     = $param->getDefaultValue();
                    $typeStyle = getTypeStyle($value);
                    $value     = \is_array($value) ? '[]' : (\is_null($value) ? 'null' : \var_export($value, true));
                }
                $default = \sprintf(' = <%s>%s</%s>', $typeStyle, OutputFormatter::escape($value), $typeStyle);
            } else {
                $default = '';
            }

            $params[] = \sprintf(
                '%s%s<strong>$%s</strong>%s',
                $param->isPassedByReference() ? '&' : '',
                $hint,
                $param->getName(),
                $default
            );
        }

        return \sprintf(
            '<keyword>function</keyword> %s<function>%s</function>(%s)',
            $reflector->returnsReference() ? '&' : '',
            $reflector->getName(),
            \implode(', ', $params)
        );
    }
}
