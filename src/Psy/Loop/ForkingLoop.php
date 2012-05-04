<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Loop;

use Psy\Configuration;
use Psy\Loop\Loop;
use Psy\Shell;

class ForkingLoop extends Loop
{
    private $parentPid;
    private $returnFile;
    private $savegameFile;
    private $savegame;

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        $this->parentPid    = posix_getpid();
        $this->returnFile   = $config->getPipe('return', $this->parentPid);
        $this->savegameFile = $config->getTempFile('savegame', $this->parentPid);
    }

    public function run(Shell $shell)
    {
        if (!posix_mkfifo($this->returnFile, 0600)) {
            echo 'Unable to open pipe: '.$this->returnFile."\n";
            die;
        }

        if (pcntl_fork()) {
            $returnPipe = fopen($this->returnFile, 'r');
            $content = array();
            while ($line = fread($returnPipe, 1024)) {
                $content[] = $line;
            }
            $content = implode('', $content);
            fclose($returnPipe);

            return unserialize($content);
        }

        $this->createMonitor();

        $return = parent::run($shell);

        // send the return value back to the main thread
        $returnPipe = fopen($this->returnFile, 'w');
        fwrite($returnPipe, serialize($return));
        fclose($returnPipe);

        exit;
    }

    public function beforeLoop()
    {
        $this->createSavegame();
    }

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
            global $depth;
            $depth++;

            // Stop and wait until savegame is needed.
            posix_kill(posix_getpid(), SIGSTOP);

            // Savegame has been resumed
            // We'll need a new monitor and savegame
            $this->createMonitor();
            $this->createSavegame();
        }

    }
}
