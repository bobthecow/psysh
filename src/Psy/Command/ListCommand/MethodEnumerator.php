<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command\ListCommand;

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

        // We can only list methods on actual class (or object) reflectors.
        if (!$reflector instanceof \ReflectionClass) {
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
            $this->getKindLabel($reflector) => $methods,
        );
    }

    /**
     * Get defined methods for the given class or object Reflector.
     *
     * @param bool       $showAll   Include private and protected methods
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
                $ret[$name] = array(
                    'name'  => $name,
                    'style' => $this->getVisibilityStyle($method),
                    'value' => $this->presentSignature($method),
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
            return 'Interface Methods';
        }

        if (method_exists($reflector, 'isTrait') && $reflector->isTrait()) {
            return 'Trait Methods';
        }

        return 'Class Methods';
    }

    /**
     * Get output style for the given method's visibility.
     *
     * @param \ReflectionMethod $method
     *
     * @return string
     */
    private function getVisibilityStyle(\ReflectionMethod $method)
    {
        if ($method->isPublic()) {
            return self::IS_PUBLIC;
        }

        if ($method->isProtected()) {
            return self::IS_PROTECTED;
        }

        return self::IS_PRIVATE;
    }
}
