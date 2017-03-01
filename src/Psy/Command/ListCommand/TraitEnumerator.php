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
 * Trait Enumerator class.
 */
class TraitEnumerator extends Enumerator
{
    /**
     * {@inheritdoc}
     */
    protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        // bail early if current PHP doesn't know about traits.
        if (!function_exists('trait_exists')) {
            return;
        }

        // only list traits when no Reflector is present.
        //
        // TODO: make a NamespaceReflector and pass that in for commands like:
        //
        //     ls --traits Foo
        //
        // ... for listing traits in the Foo namespace

        if ($reflector !== null || $target !== null) {
            return;
        }

        // only list traits if we are specifically asked
        if (!$input->getOption('traits')) {
            return;
        }

        $traits = $this->prepareTraits(get_declared_traits());

        if (empty($traits)) {
            return;
        }

        return array(
            'Traits' => $traits,
        );
    }

    /**
     * Prepare formatted trait array.
     *
     * @param array $traits
     *
     * @return array
     */
    protected function prepareTraits(array $traits)
    {
        natcasesort($traits);

        // My kingdom for a generator.
        $ret = array();

        foreach ($traits as $name) {
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
