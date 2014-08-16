<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command\ListCommand;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Property Enumerator class.
 */
class PropertyEnumerator extends Enumerator
{
    /**
     * {@inheritdoc}
     */
    protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        // only list properties when a Reflector is present.

        if ($reflector === null) {
            return;
        }

        // We can only list properties on actual class (or object) reflectors.
        if (!$reflector instanceof \ReflectionClass) {
            return;
        }

        // only list properties if we are specifically asked
        if (!$input->getOption('properties')) {
            return;
        }

        $showAll    = $input->getOption('all');
        $properties = $this->prepareProperties($this->getProperties($showAll, $reflector), $target);

        if (empty($properties)) {
            return;
        }

        $ret = array();
        $ret[$this->getKindLabel($reflector)] = $properties;

        return $ret;
    }

    /**
     * Get defined properties for the given class or object Reflector.
     *
     * @param boolean    $showAll   Include private and protected properties.
     * @param \Reflector $reflector
     *
     * @return array
     */
    protected function getProperties($showAll, \Reflector $reflector)
    {
        $properties = array();
        foreach ($reflector->getProperties() as $property) {
            if ($showAll || $property->isPublic()) {
                $properties[$property->getName()] = $property;
            }
        }

        // TODO: this should be natcasesort
        ksort($properties);

        return $properties;
    }

    /**
     * Prepare formatted property array.
     *
     * @param array $properties
     *
     * @return array
     */
    protected function prepareProperties(array $properties, $target = null)
    {
        // My kingdom for a generator.
        $ret = array();

        foreach ($properties as $name => $property) {
            if ($this->showItem($name)) {
                $fname = '$' . $name;
                $ret[$fname] = array(
                    'name'  => $fname,
                    'style' => $this->getVisibilityStyle($property),
                    'value' => $this->presentValue($property, $target),
                );
            }
        }

        return $ret;
    }

    /**
     * Get a label for the particular kind of "class" represented.
     *
     * @param \ReflectionClass $reflector
     *
     * @return string
     */
    protected function getKindLabel(\ReflectionClass $reflector)
    {
        if ($reflector->isInterface()) {
            return 'Interface Properties';
        } elseif (method_exists($reflector, 'isTrait') && $reflector->isTrait()) {
            return 'Trait Properties';
        } else {
            return 'Class Properties';
        }
    }

    /**
     * Get output style for the given property's visibility.
     *
     * @param \ReflectionProperty $property
     *
     * @return string
     */
    private function getVisibilityStyle(\ReflectionProperty $property)
    {
        if ($property->isPublic()) {
            return self::IS_PUBLIC;
        } elseif ($property->isProtected()) {
            return self::IS_PROTECTED;
        } else {
            return self::IS_PRIVATE;
        }
    }

    /**
     * Present the $target's current value for a reflection property.
     *
     * @param \ReflectionProperty $property
     * @param mixed               $target
     *
     * @return string
     */
    protected function presentValue(\ReflectionProperty $property, $target)
    {
        if (!is_object($target)) {
            // TODO: figure out if there's a way to return defaults when target
            // is a class/interface/trait rather than an object.
            return '';
        }

        $property->setAccessible(true);
        $value = $property->getValue($target);

        return $this->presentRef($value);
    }
}
