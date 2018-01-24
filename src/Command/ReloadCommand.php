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

use Psy\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reload the Psy Shell.
 */
class ReloadCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('reload')
            ->setAliases(['reload!'])
            ->setDefinition([])
            ->setDescription('Reload the current session.')
            ->setHelp(
                <<<'HELP'
Reload the current session.

<warning>Note: The PCNTL extension is required for reload to work.</warning>

e.g.
<return>>>> reload</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!function_exists('pcntl_exec')) {
            throw new RuntimeException('Unable to reload session (PCNTL is required).');
        }

        $output->writeln('<info>Reloading...</info>');

        if (!defined('PHP_BINARY')) {
            throw new RuntimeException('Unable to identify PHP binary path.');
        }

        pcntl_exec(PHP_BINARY, $_SERVER['argv']);
    }
}
