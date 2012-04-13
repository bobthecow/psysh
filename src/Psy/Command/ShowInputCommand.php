<?php

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Shell;
use Psy\ShellAware;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowInputCommand extends Command implements ShellAware
{
    private $shell;

    public function setShell(Shell $shell)
    {
        $this->shell = $shell;
    }

    protected function configure()
    {
        $this
            ->setName('show-input')
            ->setDefinition(array())
            ->setDescription('Show the contents of the code buffer for the current multi-line expression.')
            ->setHelp(<<<EOF
Show the contents of the code buffer for the current multi-line expression.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writelnnos($this->shell->getCodeBuffer(), 0, 'return');
    }
}
