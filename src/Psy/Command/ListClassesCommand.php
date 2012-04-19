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
class ListClassesCommand extends ListingCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ls-classes')
            ->setAliases(array('list-classes'))
            ->setDefinition(array(
                new InputOption('all',    'a', InputOption::VALUE_NONE,     'Include classes outside the current namespace (if any).'),
                new InputOption('long',   'l', InputOption::VALUE_NONE,     'List in long format: includes function signatures.'),

                new InputOption('grep',   'G', InputOption::VALUE_REQUIRED, 'Show classes matching the given pattern (string or regex).'),
                new InputOption('invert', 'v', InputOption::VALUE_NONE,     'Inverted search (requires --grep).'),
            ))
            ->setDescription('List or search defined classes.')
            ->setHelp(<<<EOF
List or search defined classes.
EOF
            )
        ;
    }

    protected function listThings(InputInterface $input)
    {
        return get_declared_classes();
    }
}
