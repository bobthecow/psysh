<?php

namespace Psy\Command;

use Psy\Output\ShellOutput;
use Psy\Command\ShellAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BufferCommand extends ShellAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('buffer')
            ->setAliases(array('buf'))
            ->setDefinition(array(
                new InputOption('clear', '', InputOption::VALUE_NONE, 'Clear the current buffer.'),
            ))
            ->setDescription('Show (or clear) the contents of the code input buffer.')
            ->setHelp(<<<EOF
Show the contents of the code buffer for the current multi-line expression.

Optionally, clear the buffer by passing the <info>--clear</info> option.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $buf = $this->shell->getCodeBuffer();
        if ($input->getOption('clear')) {
            $this->shell->resetCodeBuffer();
            $output->writeln($this->formatLines($buf, 'urgent'), ShellOutput::NUMBER_LINES);
        } else {
            $output->writeln($this->formatLines($buf), ShellOutput::NUMBER_LINES);
        }
    }

    protected function formatLines($lines, $type = 'return')
    {
        $template = sprintf('<%s>%%s</%s>', $type, $type);
        return array_map(function($line) use ($template) {
            return sprintf($template, $line);
        }, $lines);
    }
}
