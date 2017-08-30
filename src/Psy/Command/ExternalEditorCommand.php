<?php

namespace Psy\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExternalEditorCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('externaledit')
            ->setAliases(array('edit'))
            ->setDefinition(array(
                new InputArgument('file', InputArgument::OPTIONAL, 'The file to open for editing. If this is not given, edits a temporary file.', null),
            ))
            ->setDescription('Open an external editor. Afterwards, get produced code in input buffer.')
            ->setHelp('Set the EDITOR environment variable to something you\'d like to use.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = $input->getArgument('file');

        if ($filePath === null) {
            $filePath = tempnam(sys_get_temp_dir(), 'psysh');
        }

        $filePath = escapeshellarg($filePath);

        $pipes = array();
        $proc = proc_open((getenv('EDITOR') ?: 'nano') . " {$filePath}", array(STDIN, STDOUT, STDERR), $pipes);
        proc_close($proc);

        $editedContent = file_get_contents($filePath);

        if ($input->getArgument('file') === null) {
            @unlink($filePath);
        }

        $this->getApplication()->addInput($editedContent);
    }
}
