<?php

namespace Psy\Command;

use Composer\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ComposerCommand
 * @package Psy\Command
 */
class ComposerCommand extends Command
{
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
    protected $composerPath;

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
                new InputArgument('command-name', InputArgument::REQUIRED, ''),
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

        // extract real command name
        $tokens = preg_split('{\s+}', $input->__toString());
        $args = array();
        foreach ($tokens as $token) {
            if ($token && $token[0] !== '-') {
                $args[] = $token;
                if (count($args) >= 2) {
                    break;
                }
            }
        }
        // show help for this command if no command was found
        if (count($args) < 2) {
            return parent::run($input, $output);
        }

        $app = new Application();
        $app->setAutoExit(false);
        $input = new StringInput(implode(' ', array_slice($tokens, 1, count($tokens))));

        return $app->doRun($input, $this->output);
    }

    public function setComposerPath($path)
    {
        $this->composerPath = $path;
    }

    /**
     * @return string
     */
    protected function getSystemShell()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 'cmd.exe';
        }

        return 'bash';
    }

    protected function callComposerBootstrap()
    {
        // TODO if the path is provided as paramater use it instead
        require_once 'phar://composer.phar/src/bootstrap.php';
    }

    /**
     * @param $command
     * @return bool|string
     */
    protected function shellCommand($command)
    {
        $process = proc_open($this->getSystemShell(), $this->specification, $pipes);
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
        return @file_exists('composer.phar') or is_null($this->composerPath);
    }

    protected function installComposer()
    {
        $this->output->writeln("<info>Composer not found, downloading.</info>");
        $response = $this->shellCommand('php -r "readfile(\'https://getcomposer.org/installer\');" | php');
        $this->output->writeln("<info>$response</info>");
    }
}
