<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Presenter;

/**
 * An object Presenter.
 */
class ObjectPresenter extends RecursivePresenter
{
    const FMT = '<object>\\<<class>%s</class> <strong>#%s</strong>></object>';

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
    public function presentRef($value)
    {
        return sprintf(self::FMT, get_class($value), spl_object_hash($value));
    }

    /**
     * Present the object.
     *
     * @param object $value
     * @param int    $depth   (default: null)
     * @param int    $options One of Presenter constants
     *
     * @return string
     */
    protected function presentValue($value, $depth = null, $options = 0)
    {
        if ($depth === 0) {
            return $this->presentRef($value);
        }

        $class = new \ReflectionObject($value);
        $propertyFilter = \ReflectionProperty::IS_PUBLIC;
        if ($options & Presenter::VERBOSE) {
            $propertyFilter |= \ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED;
        }
        $props = $this->getProperties($value, $class, $propertyFilter);

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
     * @param int              $propertyFilter One of \ReflectionProperty constants
     *
     * @return array
     */
    protected function getProperties($value, \ReflectionClass $class, $propertyFilter)
    {
        $deprecated = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecated) {
            if (in_array($errno, array(E_DEPRECATED, E_USER_DEPRECATED))) {
                $deprecated = true;
            } else {
                // not a deprecation error, let someone else handle this
                return false;
            }
        });

        $props = array();
        foreach ($class->getProperties($propertyFilter) as $prop) {
            $deprecated = false;

            $prop->setAccessible(true);
            $val = $prop->getValue($value);

            if (!$deprecated) {
                $props[$this->propertyKey($prop)] = $val;
            }
        }

        restore_error_handler();

        return $props;
    }

    protected function propertyKey(\ReflectionProperty $prop)
    {
        $key = $prop->getName();
        if ($prop->isProtected()) {
            return sprintf('<protected>%s</protected>', $key);
        } elseif ($prop->isPrivate()) {
            return sprintf('<private>%s</private>', $key);
        }

        return $key;
    }
}
