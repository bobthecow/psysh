<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Presenter;

use Psy\Presenter\RecursivePresenter;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * An object Presenter.
 */
class ObjectPresenter extends RecursivePresenter
{
    const FMT       = '%s%s #%s>';
    const COLOR_FMT = '<object>%s<class>%s</class> <strong>#%s</strong>></object>';

    /**
     * ObjectPresenter can present objects.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return is_object($value);
    }

    /**
     * Present a reference to the object.
     *
     * @param object $value
     *
     * @return string
     */
    public function presentRef($value, $color = false)
    {
        $format = $color ? self::COLOR_FMT : self::FMT;

        return sprintf($format, OutputFormatter::escape('<'), get_class($value), spl_object_hash($value));
    }

    /**
     * Present the object.
     *
     * @param object $value
     * @param int    $depth (default:null)
     *
     * @return string
     */
    protected function presentValue($value, $depth = null)
    {
        if ($depth === 0) {
            return $this->presentRef($value);
        }

        $class = new \ReflectionObject($value);
        $props = $this->getProperties($value, $class);

        return sprintf('%s %s', $this->presentRef($value), $this->formatProperties($props));
    }

    /**
     * Format object properties.
     *
     * @param array $props
     *
     * @return string
     */
    protected function formatProperties($props)
    {
        if (empty($props)) {
            return '{}';
        }

        $formatted = array();
        foreach ($props as $name => $value) {
            $formatted[] = sprintf('%s: %s', $name, $this->indentValue($this->presentSubValue($value)));
        }

        $template = sprintf('{%s%s%%s%s}', PHP_EOL, self::INDENT, PHP_EOL);
        $glue     = sprintf(',%s%s', PHP_EOL, self::INDENT);

        return sprintf($template, implode($glue, $formatted));
    }

    /**
     * Get an array of object properties.
     *
     * @param object           $value
     * @param \ReflectionClass $class
     *
     * @return array
     */
    protected function getProperties($value, \ReflectionClass $class)
    {
        $deprecated = false;
        set_error_handler(function($errno, $errstr) use (&$deprecated) {
            if (in_array($errno, array(E_DEPRECATED, E_USER_DEPRECATED))) {
                $deprecated = true;
            } else {
                // not a deprecation error, let someone else handle this
                return false;
            }
        });

        $props = array();
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $deprecated = false;
            $val = $prop->getValue($value);

            if (!$deprecated) {
                $props[$prop->getName()] = $val;
            }
        }

        restore_error_handler();

        return $props;
    }
}
