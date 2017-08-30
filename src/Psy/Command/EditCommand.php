<?php

namespace Psy\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EditCommand extends Command
{
    private $temporaryDirectory = '';

    protected function configure()
    {
        $this
            ->setName('edit')
            ->setDefinition(array(
                new InputArgument('file', InputArgument::OPTIONAL, 'The file to open for editing. If this is not given, edits a temporary file.', null),
                new InputOption(
                    'exec',
                    'e',
                    InputOption::VALUE_OPTIONAL,
                    'Whether or not to execute the file content after editing. Defaults to true when no file was provided, defaults to false if a file was provided',
                    null
                ),
            ))
            ->setDescription('Open an external editor. Afterwards, get produced code in input buffer.')
            ->setHelp('Set the EDITOR environment variable to something you\'d like to use.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = $input->getArgument('file');

        if ($filePath === null) {
            $filePath = tempnam($this->temporaryDirectory, 'psysh-edit-command');
            $execute = $input->getOption('exec') ?: 'true';
        } else {
            $execute = $input->getOption('exec') ?: 'false';
        }

        $execute = $execute === 'true';

        $escapedFilePath = escapeshellarg($filePath);

        $pipes = array();
        $proc = proc_open((getenv('EDITOR') ?: 'nano') . " {$escapedFilePath}", array(STDIN, STDOUT, STDERR), $pipes);
        proc_close($proc);

        $editedContent = file_get_contents($filePath);

        if ($input->getArgument('file') === null) {
            @unlink($filePath);
        }

        if ($execute) {
            $this->getApplication()->addInput($editedContent);
        }
    }

    public function setTemporaryDirectory($tmpDir)
    {
        $this->temporaryDirectory = $tmpDir;
    }
}
