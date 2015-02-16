<?php

namespace Psy\Command;

use Psy\Configuration;
use Psy\Shell;
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
            ->setDescription('Profiles with a timer.')
            ->setUsesWholeStringInput(true)
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
        $target = $input->getLine();

        /** @var Shell $shell */
        $shell = $this->getApplication();
        $sh = new Shell(new Configuration());
        $sh->setOutput($output);
        $sh->setScopeVariables($shell->getScopeVariables());

        $start = microtime(true);
        $sh->execute($target);
        $end = microtime(true);

        $output->writeln(sprintf('<info>Command took %.6f seconds to complete.</info>', $end-$start));
    }
}
