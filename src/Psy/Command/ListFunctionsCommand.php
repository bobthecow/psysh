<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Command\ListingCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * List available local variables, object properties, etc.
 */
class ListFunctionsCommand extends ListingCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ls-functions')
            ->setAliases(array('list-functions'))
            ->setDefinition(array(
                new InputOption('all',    'a', InputOption::VALUE_NONE,     'Include functions outside the current namespace (if any).'),
                new InputOption('long',   'l', InputOption::VALUE_NONE,     'List in long format: includes function signatures.'),
                new InputOption('user',   'u', InputOption::VALUE_NONE,     'List only user-defined functions (no system functions).'),

                new InputOption('grep',   'G', InputOption::VALUE_REQUIRED, 'Show functions matching the given pattern (string or regex).'),
                new InputOption('invert', 'v', InputOption::VALUE_NONE,     'Inverted search (requires --grep).'),
            ))
            ->setDescription('List or search defined functions.')
            ->setHelp(<<<EOF
List or search defined functions.
EOF
            )
        ;
    }

    protected function listThings(InputInterface $input)
    {
        $funcs = get_defined_functions();

        if ($input->getOption('user')) {
            return $funcs['user'];
        } else {
            return array_merge($funcs['internal'], $funcs['user']);
        }
    }
}
