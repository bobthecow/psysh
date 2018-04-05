<?php

namespace Psy\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

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

    /**
     * @param $path
     */
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
     *
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

    /**
     * @return string
     */
    protected function getLocalComposerFile()
    {
        return getcwd() . DIRECTORY_SEPARATOR . 'composer.phar';
    }

    /**
     * @return bool
     */
    protected function checkComposerInstallation()
    {
        return @file_exists($this->getLocalComposerFile()) or !is_null($this->composerPath);
    }

    /**
     *
     */
    protected function installComposer()
    {
        $this->output->writeln("<info>Composer not found, downloading.</info>");
        $response = $this->shellCommand('php -r "readfile(\'https://getcomposer.org/installer\');" | php');
        $this->output->writeln("<info>$response</info>");
    }
}
