<?php

namespace Psy\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class ComposerCommand
 * @package Psy\Command
 */
class ComposerCommand extends Command
{
    /**
     * @var string
     */
    protected $composerPath;

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
                new InputArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, ''),
            ))
            ->setDescription('Composer installation.')
            ->setHelp(
                <<<HELP
composer library Installs the library

if composer is not found will install it locally
HELP
            );
        $this->ignoreValidationErrors();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        if (!$this->checkComposerInstallation()) {
            $this->installComposer();
        }

        $this->callComposerBootstrap();

        $app = new \Composer\Console\Application();
        $app->setAutoExit(false);
        $input = new StringInput(trim(preg_replace(sprintf('#^%s#', $this->getName()), '', $input->__toString())));

        return $app->doRun($input, $this->output);
    }

    public function setComposerPath($path)
    {
        $this->composerPath = $path;
    }

    /**
     *
     */
    protected function callComposerBootstrap()
    {
        $dependency = sprintf(
            'phar://%s/src/bootstrap.php',
            !is_null($this->composerPath) ?  $this->composerPath : $this->getLocalComposerFile()
        );

        require_once $dependency;
    }

    /**
     * @param $command
     * @return bool|string
     */
    protected function shellCommand($command)
    {
        /** PHP 5.4 compatibility, closures have no this scope in 5.4 versions */
        $output = $this->output;
        $process = new Process($command);
        $process->run(function ($type, $buffer) use ($output) {
            if (Process::ERR === $type) {
                $this->output->writeln("<error>$buffer</error>");
            }
        });

        return $process->getOutput();
    }

    protected function getLocalComposerFile()
    {
        return getcwd() . DIRECTORY_SEPARATOR . 'composer.phar';
    }

    protected function checkComposerInstallation()
    {
        return @file_exists($this->getLocalComposerFile()) or !is_null($this->composerPath);
    }

    protected function installComposer()
    {
        $this->output->writeln("<info>Composer not found, downloading.</info>");
        $response = $this->shellCommand('php -r "readfile(\'https://getcomposer.org/installer\');" | php');
        $this->output->writeln("<info>$response</info>");
    }
}
