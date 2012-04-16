<?php

namespace Psy\Command;

use Psy\Command\TraceCommand;
use Psy\Output;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WtfCommand extends TraceCommand
{
    protected function configure()
    {
        $this
            ->setName('wtf')
            ->setAliases(array('last-exception', 'wtf?'))
            ->setDefinition(array(
                new InputArgument('incredulity', InputArgument::OPTIONAL, 'Number of lines to show'),

                new InputOption('verbose', 'v',  InputOption::VALUE_NONE, 'Show entire backtrace.'),
            ))
            ->setDescription('Show the backtrace of the most recent exception.')
            ->setHelp(<<<EOF
Shows a few lines of the backtrace of the most recent exception.

If you want to see more lines, add more question marks or exclamation marks:

e.g.
<return>>>> wtf ?</return>
<return>>>> wtf ?!???!?!?</return>

To see the entire backtrace, pass the -v/--verbose flag:

e.g.
<return>>>> wtf -v</return>
EOF
            )
        ;
    }

    protected function getHiddenOptions()
    {
        $options = parent::getHiddenOptions();
        unset($options[array_search('verbose', $options)]);

        return $options;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $incredulity = $input->getArgument('incredulity');
        if (strlen(preg_replace('/[\\?!]/', '', $incredulity))) {
            throw new \InvalidArgumentException('Incredulity must include only "?" and "!".');
        }

        $count = $input->getOption('verbose') ? PHP_INT_MAX : (strlen($incredulity) + 1);
        $output->writeln($this->getBacktrace($this->getLastException(), $count), Output::NUMBER_LINES);
    }

    protected function getLastException()
    {
        $e = $this->shell->getLastException();
        if (!$e) {
            throw new \InvalidArgumentException('No most-recent exception');
        }
    }
}
