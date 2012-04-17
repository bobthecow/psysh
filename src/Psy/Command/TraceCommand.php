<?php

namespace Psy\Command;

use Psy\Command\ShellAwareCommand;
use Psy\Output\ShellOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TraceCommand extends ShellAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('trace')
            ->setDefinition(array(
                new InputOption('include-psy', 'p', InputOption::VALUE_NONE, 'Include Psy in the call stack.'),
            ))
            ->setDescription('Show the current call stack.')
            ->setHelp(<<<EOF
Show the current call stack.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->page($this->getBacktrace(new \Exception, null, $input->getOption('include-psy')), ShellOutput::NUMBER_LINES);
    }

    protected function getBacktrace(\Exception $e, $count = null, $includePsy = true)
    {
        if ($count === null) {
            $count = PHP_INT_MAX;
        }

        $lines = array();

        $trace = $e->getTrace();
        array_unshift($trace, array(
            'function' => '',
            'file'     => $e->getFile() != null ? $e->getFile() : 'n/a',
            'line'     => $e->getLine() != null ? $e->getLine() : 'n/a',
            'args'     => array(),
        ));

        if (!$includePsy) {
            for ($i = count($trace) - 1; $i >= 0; $i--) {
                $thing = isset($trace[$i]['class']) ? $trace[$i]['class'] : $trace[$i]['function'];
                if (preg_match('/\\\\?Psy\\\\/', $thing)) {
                    $trace = array_slice($trace, $i + 1);
                    break;
                }
            }
        }

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
