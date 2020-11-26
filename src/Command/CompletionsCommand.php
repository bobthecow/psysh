<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Input\CodeArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dump an array of possible completions for the given input.
 */
class CompletionsCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('completions')
            ->setDefinition([
                new CodeArgument('target', CodeArgument::OPTIONAL, 'PHP code to complete.'),
            ])
            ->setDescription('List possible code completions for the input.')
            ->setHelp(
                <<<'HELP'
This command enables PsySH wrappers to obtain completions for the current
input, for the purpose of implementing their own completion UI.
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $target = $input->getArgument('target');
        if (!isset($target)) {
            $target = '';
        }

        // n.b. All of the relevant parts of \Psy\Shell are protected
        // or private, so getTabCompletions() itself is a Shell method.
        $completions = $this->getApplication()->getTabCompletions($target);

        // Ouput the completion candidates as newline-separated text.
        $str = \implode("\n", \array_filter($completions))."\n";
        $output->write($str, false, OutputInterface::OUTPUT_RAW);

        return 0;
    }
}
