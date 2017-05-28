<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Configuration;
use Psy\Input\CodeArgument;
use Psy\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TimeitCommand.
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
            ->setDefinition([
                new CodeArgument('code', InputArgument::REQUIRED, 'Code to execute.'),
            ])
            ->setDescription('Profiles with a timer.')
            ->setHelp(
                <<<'HELP'
Time profiling for functions and commands.

e.g.
<return>>>> timeit $closure()</return>
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

        $output->writeln(sprintf('<info>Command took %.6f seconds to complete.</info>', $end - $start));
    }
}
