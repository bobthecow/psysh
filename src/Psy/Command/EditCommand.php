<?php

namespace Psy\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EditCommand extends Command
{
    private $runtimeDir = '';

    /**
     * Constructor.
     *
     * @param string      $runtimeDir The directory to use for temporary files
     * @param string|null $name       The name of the command; passing null means it must be set in configure()
     *
     * @throws \Symfony\Component\Console\Exception\LogicException When the command name is empty
     */
    public function __construct($runtimeDir, $name = null)
    {
        parent::__construct($name);

        $this->runtimeDir = $runtimeDir;
    }

    protected function configure()
    {
        $this
            ->setName('edit')
            ->setDefinition(array(
                new InputArgument('file', InputArgument::OPTIONAL, 'The file to open for editing. If this is not given, edits a temporary file.', null),
                new InputOption(
                    'exec',
                    'e',
                    InputOption::VALUE_NONE,
                    'Execute the file content after editing. This is the default when a file name argument is not given.',
                    null
                ),
                new InputOption(
                    'no-exec',
                    'E',
                    InputOption::VALUE_NONE,
                    'Do not execute the file content after editing. This is the default when a file name argument is given.',
                    null
                ),
            ))
            ->setDescription('Open an external editor. Afterwards, get produced code in input buffer.')
            ->setHelp('Set the EDITOR environment variable to something you\'d like to use.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('exec') && $input->getOption('no-exec')) {
            throw new \InvalidArgumentException('The --exec and --no-exec flags are mutually exclusive.');
        }

        $filePath = $input->getArgument('file');
        $execute = $filePath === null;

        if ($filePath === null) {
            $filePath = tempnam($this->runtimeDir, 'psysh-edit-command');
        }

        if ($input->getOption('exec')) {
            $execute = true;
        } elseif ($input->getOption('no-exec')) {
            $execute = false;
        }

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
}
