<?php

namespace Psy\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExternalEditorCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('externaledit')
            ->setAliases(array('edit'))
            ->setDescription('Open an external editor. Afterwards, get produced code in input buffer.')
            ->setHelp('Set the EDITOR environment variable to something you\'d like to use.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tmpFilePath = tempnam(sys_get_temp_dir(), 'psysh');

        $pipes = [];
        $proc = proc_open((getenv('EDITOR') ?: 'nano') . " {$tmpFilePath}", [STDIN, STDOUT, STDERR], $pipes);
        proc_close($proc);

        $editedContent = file_get_contents($tmpFilePath);
        unlink($tmpFilePath);
        $this->getApplication()->addInput($editedContent);
    }
}