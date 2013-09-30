<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Listener;

use Psy\Exception\ParseErrorException;
use Psy\Listener;
use Psy\Shell;

class Reloader implements Listener
{
    private $timestamps = array();

    /**
     * Only enabled if Runkit is installed.
     */
    public function enabled()
    {
        return extension_loaded('runkit');
    }

    /**
     * No-op.
     */
    public function onBeforeLoop(Shell $shell)
    {
    }

    /**
     * No-op.
     */
    public function onAfterLoop(Shell $shell)
    {
    }

    /**
     * Looks through included files and updates anything with a new timestamp.
     */
    public function onExecute(Shell $shell, $command)
    {
        clearstatcache();

        foreach (get_included_files() as $file) {
            if (!isset($this->timestamps[$file])) {
                $this->timestamps[$file] = filemtime($file);
                continue;
            }
            $timestamp = filemtime($file);

            if ($this->timestamps[$file] === $timestamp) {
                continue;
            }

            if (!runkit_lint_file($file)) {
                // Seriously, Runkit? What's even the point of a userspace
                // linter if you're just gonna spew errors all over the place?
                $error = "Modified file {$file} could not be reloaded";
                $shell->writeException(new ParseErrorException($error));
                continue;
            }
            runkit_import($file, (
                RUNKIT_IMPORT_FUNCTIONS |
                RUNKIT_IMPORT_CLASSES |
                RUNKIT_IMPORT_CLASS_METHODS |
                RUNKIT_IMPORT_CLASS_CONSTS |
                RUNKIT_IMPORT_CLASS_PROPS |
                RUNKIT_IMPORT_OVERRIDE
            ));
            $this->timestamps[$file] = $timestamp;
        }
        restore_error_handler();
    }
}
