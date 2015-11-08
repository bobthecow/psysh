<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command\ListCommand;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Constant Enumerator class.
 */
class ConstantEnumerator extends Enumerator
{
    /**
     * {@inheritdoc}
     */
    protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        // only list constants when no Reflector is present.
        //
        // TODO: make a NamespaceReflector and pass that in for commands like:
        //
        //     ls --constants Foo
        //
        // ... for listing constants in the Foo namespace
        if ($reflector !== null || $target !== null) {
            return;
        }

        // only list constants if we are specifically asked
        if (!$input->getOption('constants')) {
            return;
        }

        $category  = $input->getOption('user') ? 'user' : $input->getOption('category');
        $label     = $category ? ucfirst($category) . ' Constants' : 'Constants';
        $constants = $this->prepareConstants($this->getConstants($category));

        if (empty($constants)) {
            return;
        }

        $ret = array();
        $ret[$label] = $constants;

        return $ret;
    }

    /**
     * Get defined constants.
     *
     * Optionally restrict constants to a given category, e.g. "date".
     *
     * @param string $category
     *
     * @return array
     */
    protected function getConstants($category = null)
    {
        if (!$category) {
            return get_defined_constants();
        }

        $consts = get_defined_constants(true);

        return isset($consts[$category]) ? $consts[$category] : array();
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

        $names = array_keys($constants);
        natcasesort($names);

        foreach ($names as $name) {
            if ($this->showItem($name)) {
                $ret[$name] = array(
                    'name'  => $name,
                    'style' => self::IS_CONSTANT,
                    'value' => $this->presentRef($constants[$name]),
                );
            }
        }

        return $ret;
    }
}
