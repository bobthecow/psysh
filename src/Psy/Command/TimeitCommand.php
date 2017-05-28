<?php

namespace Psy\Command;

use Psy\Configuration;
use Psy\Input\CodeArgument;
use Psy\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TimeitCommand
 * @package Psy\Command
 */
class TimeitCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('timeit')
            ->setDefinition(array(
                new CodeArgument('code', InputArgument::REQUIRED, 'Code to execute.'),
            ))
            ->setDescription('Profiles with a timer.')
            ->setHelp(
                <<<HELP
Time profiling for functions and commands.

e.g.
<return>>>> timeit \$closure()</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $code = $input->getArgument('code');

        /** @var Shell $shell */
        $shell = $this->getApplication();
        $sh = new Shell(new Configuration());
        $sh->setOutput($output);
        $sh->setScopeVariables($shell->getScopeVariables());

        $start = microtime(true);
        $sh->execute($code);
        $end = microtime(true);

        $output->writeln(sprintf('<info>Command took %.6f seconds to complete.</info>', $end-$start));
    }
}
