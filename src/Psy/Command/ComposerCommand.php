<?php

namespace Psy\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ComposerCommand
 * @package Psy\Command
 */
class ComposerCommand extends Command
{
    const COMPOSER_IS_GLOBAL = 'global';
    const COMPOSER_IS_LOCAL = 'local';

    const PIPE_WRITE = 0;
    const PIPE_READ = 1;
    const PIPE_ERR = 2;

    /**
     * @var array
     */
    protected $specification = array(
        self::PIPE_WRITE => array("pipe", "r"),
        self::PIPE_READ => array("pipe", "w"),
        self::PIPE_ERR => array("pipe", "w"),
    );

    /**
     * @var string
     */
    protected $installationType;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('composer')
            ->setDefinition(array(
                new InputArgument('library', InputArgument::REQUIRED, 'library to install'),
            ))
            ->setDescription('Composer installation.')
            ->setHelp(
                <<<HELP
composer library Installs the library

if composer is not found will install it locally
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $library = $this->input->getArgument('library');

        if (!$this->checkComposerInstallation()) {
            $this->installComposer();
        }

        $this->installLibrary($library);
    }

    protected function getComposerPath()
    {
        if ($this->installationType === self::COMPOSER_IS_LOCAL) {
            return 'php composer.phar';
        }

        return 'composer';
    }

    protected function bashCommand($command)
    {
        $process = proc_open('bash', $this->specification, $pipes);
        stream_set_blocking($pipes[self::PIPE_ERR], 0);

        if (is_resource($process)) {
            fwrite($pipes[self::PIPE_WRITE], $command);
            fclose($pipes[self::PIPE_WRITE]);

            if ($err = stream_get_contents($pipes[self::PIPE_ERR])) {
                $err = trim($err);
                $this->output->writeln("<error>$err</error>");

                return false;
            }

            $return = stream_get_contents($pipes[self::PIPE_READ]);
            fclose($pipes[self::PIPE_READ]);

            if (proc_close($process) === 0) {
                return trim($return);
            }
        }

        return false;
    }

    protected function checkComposerInstallation()
    {
        $whichComposer = $this->bashCommand('which composer');

        if (! $whichComposer) {
            // it will be local for sure
            $this->installationType = self::COMPOSER_IS_LOCAL;

            // does it exists locally?
            return file_exists('composer.phar');
        }

        $this->installationType = self::COMPOSER_IS_GLOBAL;

        return true;
    }

    protected function installComposer()
    {
        $this->output->writeln("<info>Composer not found, installing locally.</info>");
        $response = $this->bashCommand('php -r "readfile(\'https://getcomposer.org/installer\');" | php');
        $this->output->writeln("<info>$response</info>");
    }

    protected function installLibrary($library)
    {
        // require
        $composer = $this->getComposerPath();

        $this->output->writeln("<info>Require $library</info>");
        $this->bashCommand("$composer require '$library'");
        $this->output->writeln("<info>composer update</info>");
        $this->bashCommand("$composer update");
        $this->output->writeln("<info>dumping autoload</info>");
        $this->bashCommand("$composer dump-autoload");
        require_once 'vendor/autoload.php';
    }
}
