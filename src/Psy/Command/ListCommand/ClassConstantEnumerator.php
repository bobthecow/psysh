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

        // only list constants if we are specifically asked
        if (!$input->getOption('constants')) {
            return;
        }

        $constants = $this->prepareConstants($this->getConstants($reflector));

        if (empty($constants)) {
            return;
        }

        return array(
            'Constants' => $constants,
        );
    }

    /**
     * Get defined constants for the given class or object Reflector.
     *
     * @param \Reflector $reflector
     *
     * @return array
     */
    protected function getConstants(\Reflector $reflector)
    {
        $constants = array();
        foreach ($reflector->getConstants() as $name => $constant) {
            $constants[$name] = new ReflectionConstant($reflector, $name);
        }

        // TODO: this should be natcasesort
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
                    'name'       => $name,
                    'visibility' => self::IS_PUBLIC,
                    'value'      => $this->presentSignature($constant),
                );
            }
        }

        return $ret;
    }
}
