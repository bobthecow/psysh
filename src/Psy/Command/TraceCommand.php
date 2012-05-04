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

use Psy\Command\ShellAwareCommand;
use Psy\Output\ShellOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show the current stack trace.
 */
class TraceCommand extends ShellAwareCommand
{
    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->page($this->getBacktrace(new \Exception, null, $input->getOption('include-psy')), ShellOutput::NUMBER_LINES);
    }

    /**
     * Get a backtrace for an exception.
     *
     * Optionally limit the number of rows to include with $count, and exclude
     * Psy from the trace.
     *
     * @param \Exception $e
     * @param int        $count      (default: PHP_INT_MAX)
     * @param bool       $includePsy (default: true)
     *
     * @return array Formatted stacktrace lines.
     */
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
