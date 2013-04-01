<?php

/*
 * This file is part of PsySH
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Command\ShellAware;
use Psy\Command\TraceCommand;
use Psy\Output\ShellOutput;
use Psy\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show the last uncaught exception.
 */
class WtfCommand extends TraceCommand implements ShellAware
{
    /**
     * Shell instance (for ShellAware interface)
     *
     * @type Psy\Shell
     */
    protected $shell;

    /**
     * ShellAware interface.
     *
     * @param Psy\Shell $shell
     */
    public function setShell(Shell $shell)
    {
        $this->shell = $shell;
    }

    /**
     * {@inheritdoc}
     */
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
            );
    }

    /**
     * --verbose is not hidden for this option :)
     *
     * @return array
     */
    protected function getHiddenOptions()
    {
        $options = parent::getHiddenOptions();
        unset($options[array_search('verbose', $options)]);

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $incredulity = $input->getArgument('incredulity');
        if (strlen(preg_replace('/[\\?!]/', '', $incredulity))) {
            throw new \InvalidArgumentException('Incredulity must include only "?" and "!".');
        }

        $count = $input->getOption('verbose') ? PHP_INT_MAX : (strlen($incredulity) + 1);
        $output->page($this->getBacktrace($this->getLastException(), $count), ShellOutput::NUMBER_LINES);
    }

    /**
     * Grab the last uncaught exception from the shell.
     *
     * @return \Exception
     */
    protected function getLastException()
    {
        $e = $this->shell->getLastException();
        if (!$e) {
            throw new \InvalidArgumentException('No most-recent exception');
        }

        return $e;
    }
}
