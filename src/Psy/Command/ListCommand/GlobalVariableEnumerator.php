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
 * Global Variable Enumerator class.
 */
class GlobalVariableEnumerator extends Enumerator
{
    /**
     * {@inheritdoc}
     */
    protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        // only list globals when no Reflector is present.
        if ($reflector !== null || $target !== null) {
            return;
        }

        // only list globals if we are specifically asked
        if (!$input->getOption('globals')) {
            return;
        }

        $globals = $this->prepareGlobals($this->getGlobals());

        if (empty($globals)) {
            return;
        }

        return array(
            'Global Variables' => $globals,
        );
    }

    /**
     * Get defined global variables.
     *
     * @return array
     */
    protected function getGlobals()
    {
        global $GLOBALS;

        $names = array_keys($GLOBALS);
        natcasesort($names);

        $ret = array();
        foreach ($names as $name) {
            $ret[$name] = $GLOBALS[$name];
        }

        return $ret;
    }

    /**
     * Prepare formatted global variable array.
     *
     * @param array $globals
     *
     * @return array
     */
    protected function prepareGlobals($globals)
    {
        // My kingdom for a generator.
        $ret = array();

        foreach ($globals as $name => $value) {
            if ($this->showItem($name)) {
                $fname = '$' . $name;
                $ret[$fname] = array(
                    'name'  => $fname,
                    'style' => self::IS_GLOBAL,
                    'value' => $this->presentRef($value),
                );
            }
        }

        return $ret;
    }
}
