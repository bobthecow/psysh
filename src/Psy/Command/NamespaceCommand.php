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

use Psy\Output\ShellOutput;
use Psy\Command\ShellAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NamespaceCommand extends ShellAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('namespace')
            ->setDefinition(array(
                new InputArgument('namespace', InputArgument::OPTIONAL, 'The namespace.'),
                new InputOption('clear', '',   InputOption::VALUE_NONE, 'Clear the current namespace.'),
            ))
            ->setDescription('Set (or clear) the current namespace.')
            ->setHelp(<<<EOF
Set (or clear) the current namespace.
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('clear')) {
            $this->shell->setNamespace(null);
            $output->writeln('<aside>Namespace cleared</aside>');
        } elseif ($namespace = $input->getArgument('namespace')) {
            $this->shell->setNamespace($namespace);
        } elseif ($namespace = $this->shell->getNamespace()) {
            $output->writeln(sprintf('<info>%s</info>', $namespace));
        }
    }
}
