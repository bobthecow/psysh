<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command\ListCommand;

use Psy\Command\ListCommand\Enumerator;
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

        // only list properties if we are specifically asked
        if (!$input->getOption('properties')) {
            return;
        }

        $showAll = $input->getOption('all');

        $properties = $this->prepareProperties($this->getProperties($showAll, $reflector));

        if (empty($properties)) {
            return;
        }

        return array(
            'Properties' => $properties,
        );
    }

    /**
     * Get defined properties for the given class or object Reflector.
     *
     * @param boolean    $showAll Include private and protected properties.
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
    protected function prepareProperties(array $properties)
    {
        // My kingdom for a generator.
        $ret = array();

        foreach ($properties as $name => $property) {
            if ($this->showItem($name)) {
                if ($property->isPublic()) {
                    $visibility = self::IS_PUBLIC;
                } elseif ($property->isProtected()) {
                    $visibility = self::IS_PROTECTED;
                } else {
                    $visibility = self::IS_PRIVATE;
                }

                $fname = '$' . $name;
                $ret[$fname] = array(
                    'name'       => $fname,
                    'visibility' => $visibility,
                    'value'      => $this->presentSignature($property), // TODO: add types to property signatures
                );
            }
        }

        return $ret;
    }
}
