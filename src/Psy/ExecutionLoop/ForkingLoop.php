<?php

/*
 * This file is part of PsySH
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\ExecutionLoop;

use Psy\Configuration;
use Psy\ExecutionLoop\Loop;
use Psy\Shell;

/**
 * A forking version of the Psy shell execution loop.
 *
 * This version is preferred, as it won't die prematurely if user input includes
 * a fatal error, such as redeclaring a class or function.
 */
class ForkingLoop extends Loop
{
    private $parentPid;
    private $returnFile;
    private $savegameFile;
    private $savegame;

    /**
     * Constructor.
     *
     * @param Configuration $config
     */
    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        $this->parentPid    = posix_getpid();
        $this->returnFile   = $config->getPipe('return', $this->parentPid);
        $this->savegameFile = $config->getTempFile('savegame', $this->parentPid);
    }

    /**
     * Run the exection loop.
     *
     * @param Shell $shell
     */
    public function run(Shell $shell)
    {
        if (!posix_mkfifo($this->returnFile, 0600)) {
            echo 'Unable to open pipe: '.$this->returnFile."\n";
            die;
        }

        if (pcntl_fork()) {
            // This is the main thread.
            // Open the return pipe and wait for a child process to exit.
            $returnPipe = fopen($this->returnFile, 'r');
            $content = array();
            while ($line = fread($returnPipe, 1024)) {
                $content[] = $line;
            }
            $content = implode('', $content);
            fclose($returnPipe);

            $shell->setScopeVariables(unserialize($content));

            return;
        }

        // This is the child process.
        // Fork a monitor process (which will be responsible for restarting from
        // a savegame in the event of fail).
        $this->createMonitor();

        parent::run($shell);

        $return = $shell->getScopeVariables();

        // Send the return value back to the main thread
        $returnPipe = fopen($this->returnFile, 'w');
        fwrite($returnPipe, $this->serializeReturn($return));
        fclose($returnPipe);

        exit;
    }

    /**
     * Create a savegame at the start of each loop iteration.
     */
    public function beforeLoop()
    {
        $this->createSavegame();
    }

    /**
     * Create a monitor process.
     *
     * This process sits above the worker process and waits for it to exit. If
     * the worker did not exit cleanly — for example, if a PHP fatal error was
     * encountered — the monitor will resume the latest savegame.
     */
    private function createMonitor()
    {
        $pid = pcntl_fork();
        if ($pid) {
            pcntl_waitpid($pid, $status);
            if (!pcntl_wexitstatus($status)) {
                // if the worker exited successfully, kill the monitor.
                posix_kill(posix_getpid(), SIGKILL);
            }

            // Otherwise, find a savegame to restore.
            $savegamePid = trim(file_get_contents($this->savegameFile));

            if (empty($savegamePid)) {
                echo "Savegame not found.\n";
                die;
            }

            // Restart the savegame and kill the monitor.
            posix_kill($savegamePid, SIGCONT);
            posix_kill(posix_getpid(), SIGKILL);
        }
    }

    /**
     * Create a savegame fork.
     *
     * The savegame contains the current execution state, and can be resumed in
     * the event that the worker dies unexpectedly (for example, by encountering
     * a PHP fatal error).
     */
    private function createSavegame()
    {
        if (isset($this->savegame)) {
            posix_kill($this->savegame, SIGKILL);
        }

        $pid = pcntl_fork();

        if ($pid) {
            // Save the savegame PID for later
            $this->savegame = $pid;
            file_put_contents($this->savegameFile, $this->savegame);
        } else {
            // Stop and wait until savegame is needed.
            posix_kill(posix_getpid(), SIGSTOP);

            // Savegame has been resumed
            // We'll need a new monitor and savegame
            $this->createMonitor();
            $this->createSavegame();
        }
    }

    /**
     * Serialize all serializable return values.
     *
     * A naïve serialization will run into issues if there is a Closure or
     * SimpleXMLElement (among other things) in scope when exiting the execution
     * loop. We'll just ignore these unserializable classes, and serialize what
     * we can.
     *
     * @param  array  $return
     * @return string
     */
    private function serializeReturn(array $return)
    {
        $serializable = array();
        foreach ($return as $key => $value) {
            try {
                serialize($value);
                $serializable[$key] = $value;
            } catch (\Exception $e) {
                // we'll just ignore this one...
            }
        }

        return serialize($serializable);
    }
}
