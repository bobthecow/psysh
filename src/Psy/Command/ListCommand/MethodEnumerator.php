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
 * Method Enumerator class.
 */
class MethodEnumerator extends Enumerator
{
    /**
     * {@inheritdoc}
     */
    protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        // only list methods when a Reflector is present.

        if ($reflector === null) {
            return;
        }

        // only list methods if we are specifically asked
        if (!$input->getOption('methods')) {
            return;
        }

        $showAll = $input->getOption('all');

        $methods = $this->prepareMethods($this->getMethods($showAll, $reflector));

        if (empty($methods)) {
            return;
        }

        return array(
            'Methods' => $methods,
        );
    }

    /**
     * Get defined methods for the given class or object Reflector.
     *
     * @param boolean    $showAll Include private and protected methods.
     * @param \Reflector $reflector
     *
     * @return array
     */
    protected function getMethods($showAll, \Reflector $reflector)
    {
        $methods = array();
        foreach ($reflector->getMethods() as $name => $method) {
            if ($showAll || $method->isPublic()) {
                $methods[$method->getName()] = $method;
            }
        }

        // TODO: this should be natcasesort
        ksort($methods);

        return $methods;
    }

    /**
     * Prepare formatted method array.
     *
     * @param array $methods
     *
     * @return array
     */
    protected function prepareMethods(array $methods)
    {
        // My kingdom for a generator.
        $ret = array();

        foreach ($methods as $name => $method) {
            if ($this->showItem($name)) {
                if ($method->isPublic()) {
                    $visibility = self::IS_PUBLIC;
                } elseif ($method->isProtected()) {
                    $visibility = self::IS_PROTECTED;
                } else {
                    $visibility = self::IS_PRIVATE;
                }

                $ret[$name] = array(
                    'name'       => $name,
                    'visibility' => $visibility,
                    'value'      => $this->presentSignature($method),
                );
            }
        }

        return $ret;
    }
}
