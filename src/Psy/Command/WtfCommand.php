<?php

namespace Psy\Command;

use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WtfCommand extends ShellAwareCommand
{
    private $specialVars = array('_', 'this');

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
Show's a few lines of the backtrace of the most recent exception.

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
        $output->writelnnos($this->getBacktrace($count));
    }

    private function getBacktrace($count)
    {
        $e = $this->shell->getLastException();
        if (!$e) {
            throw new \InvalidArgumentException('No most-recent exception');
        }

        $lines = array();

        $trace = $e->getTrace();
        array_unshift($trace, array(
            'function' => '',
            'file'     => $e->getFile() != null ? $e->getFile() : 'n/a',
            'line'     => $e->getLine() != null ? $e->getLine() : 'n/a',
            'args'     => array(),
        ));

        for ($i = 0, $count = min($count, count($trace)); $i < $count; $i++) {
            $class    = isset($trace[$i]['class']) ? $trace[$i]['class'] : '';
            $type     = isset($trace[$i]['type']) ? $trace[$i]['type'] : '';
            $function = $trace[$i]['function'];
            $file     = isset($trace[$i]['file']) ? $trace[$i]['file'] : 'n/a';
            $line     = isset($trace[$i]['line']) ? $trace[$i]['line'] : 'n/a';

            $lines[] = sprintf(' %s%s%s() at <info>%s:%s</info>', $class, $type, $function, $file, $line);
        }

        return $lines;
    }
}
