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
 * Class Enumerator class.
 */
class ClassEnumerator extends Enumerator
{
    /**
     * {@inheritdoc}
     */
    protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        // only list classes when no Reflector is present.
        //
        // TODO: make a NamespaceReflector and pass that in for commands like:
        //
        //     ls --classes Foo
        //
        // ... for listing classes in the Foo namespace

        if ($reflector !== null || $target !== null) {
            return;
        }

        // only list classes if we are specifically asked
        if (!$input->getOption('classes')) {
            return;
        }

        $classes = $this->prepareClasses(get_declared_classes());

        if (empty($classes)) {
            return;
        }

        return array(
            'Classes' => $classes,
        );
    }

    /**
     * Prepare formatted class array.
     *
     * @param array $class
     *
     * @return array
     */
    protected function prepareClasses(array $classes)
    {
        natcasesort($classes);

        // My kingdom for a generator.
        $ret = array();

        foreach ($classes as $name) {
            if ($this->showItem($name)) {
                $ret[$name] = array(
                    'name'  => $name,
                    'style' => self::IS_CLASS,
                    'value' => $this->presentSignature($name),
                );
            }
        }

        return $ret;
    }
}
