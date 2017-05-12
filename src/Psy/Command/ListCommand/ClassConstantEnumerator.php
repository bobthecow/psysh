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

use Psy\Reflection\ReflectionConstant;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class Constant Enumerator class.
 */
class ClassConstantEnumerator extends Enumerator
{
    /**
     * {@inheritdoc}
     */
    protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        // only list constants when a Reflector is present.

        if ($reflector === null) {
            return;
        }

        // We can only list constants on actual class (or object) reflectors.
        if (!$reflector instanceof \ReflectionClass) {
            // @todo handle ReflectionExtension as well
            return;
        }

        // only list constants if we are specifically asked
        if (!$input->getOption('constants')) {
            return;
        }

        $noInherit = $input->getOption('no-inherit');
        $constants = $this->prepareConstants($this->getConstants($reflector, $noInherit));

        if (empty($constants)) {
            return;
        }

        $ret = array();
        $ret[$this->getKindLabel($reflector)] = $constants;

        return $ret;
    }

    /**
     * Get defined constants for the given class or object Reflector.
     *
     * @param \Reflector $reflector
     * @param bool       $noInherit Exclude inherited constants
     *
     * @return array
     */
    protected function getConstants(\Reflector $reflector, $noInherit = false)
    {
        $className = $reflector->getName();

        $constants = array();
        foreach ($reflector->getConstants() as $name => $constant) {
            $constReflector = new ReflectionConstant($reflector, $name);

            if ($noInherit && $constReflector->getDeclaringClass()->getName() !== $className) {
                continue;
            }

            $constants[$name] = $constReflector;
        }

        // @todo this should be natcasesort
        ksort($constants);

        return $constants;
    }

    /**
     * Prepare formatted constant array.
     *
     * @param array $constants
     *
     * @return array
     */
    protected function prepareConstants(array $constants)
    {
        // My kingdom for a generator.
        $ret = array();

        foreach ($constants as $name => $constant) {
            if ($this->showItem($name)) {
                $ret[$name] = array(
                    'name'  => $name,
                    'style' => self::IS_CONSTANT,
                    'value' => $this->presentRef($constant->getValue()),
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
            return 'Interface Constants';
        } elseif (method_exists($reflector, 'isTrait') && $reflector->isTrait()) {
            return 'Trait Constants';
        } else {
            return 'Class Constants';
        }
    }
}
