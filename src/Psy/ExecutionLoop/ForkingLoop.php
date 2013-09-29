<?php

/*
 * This file is part of Psy Shell
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
 * A forking version of the Psy Shell execution loop.
 *
 * This version is preferred, as it won't die prematurely if user input includes
 * a fatal error, such as redeclaring a class or function.
 */
class ForkingLoop extends Loop
{
    private $returnFile;
    private $savegame;

    /**
     * Constructor.
     *
     * @param Configuration $config
     */
    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        $this->returnFile = $config->getPipe('return', posix_getpid());
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
        if (function_exists('setproctitle')) {
            setproctitle('psysh (loop)');
        }

        // Let's do some processing.
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
     * Create a savegame fork.
     *
     * The savegame contains the current execution state, and can be resumed in
     * the event that the worker dies unexpectedly (for example, by encountering
     * a PHP fatal error).
     */
    private function createSavegame()
    {
        $pid = posix_getpid();

        // if there's an old savegame hanging around, let's kill it.
        if (isset($this->savegame) && $this->savegame !== $pid) {
            posix_kill($this->savegame, SIGKILL);
        }

        // the current process will become the savegame
        $this->savegame = $pid;

        $childPid = pcntl_fork();
        if ($childPid < 0) {
            throw new \RuntimeException('Unable to create savegame fork.');
        } elseif ($childPid > 0) {
            // we're the savegame now... let's wait and see what happens
            pcntl_waitpid($childPid, $status);

            // worker exited cleanly, let's bail
            if (!pcntl_wexitstatus($status)) {
                posix_kill(posix_getpid(), SIGKILL);
            }

            // worker didn't exit cleanly, we'll need to have another go
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
