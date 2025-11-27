<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\Command\Command;
use Psy\Shell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestableCommand extends Command
{
    protected function configure()
    {
        $this->setName('testable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }

    public function publicGetTable(OutputInterface $output)
    {
        return $this->getTable($output);
    }

    public function publicGetShell(): Shell
    {
        return $this->getShell();
    }
}
