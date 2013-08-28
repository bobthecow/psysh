<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Command\ListingCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * List named constants.
 */
class ListDefinesCommand extends ListingCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ls-defines')
            ->setAliases(array('list-defines'))
            ->setDefinition(array(
                new InputOption('long',     'l', InputOption::VALUE_NONE,     'List in long format.'),
                new InputOption('user',     'u', InputOption::VALUE_NONE,     'List all user-defined constants.'),
                new InputOption('category', 'C', InputOption::VALUE_REQUIRED, 'List all defined constants from a specific category.'),

                new InputOption('grep',   'G', InputOption::VALUE_REQUIRED, 'Show constants matching the given pattern (string or regex).'),
                new InputOption('invert', 'v', InputOption::VALUE_NONE,     'Inverted search (requires --grep).'),
            ))
            ->setDescription('List or search named constants.')
            ->setHelp(<<<EOF
List or search named constants.
EOF
            );
    }

    /**
     * Get defined constants
     *
     * @param InputInterface $input
     *
     * @return array
     */
    protected function listThings(InputInterface $input)
    {

        if ($input->getOption('user')) {
            $consts = get_defined_constants(true);
            return isset($consts['user']) ? array_keys($consts['user']) : array();

        } elseif ( $category = $input->getOption('category') ) {
            $consts = get_defined_constants(true);
            return isset($consts[$category]) ? array_keys($consts[$category]) : array();
        } else {

            return array_keys(get_defined_constants());
        }
    }
}
