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
use Psy\ParserFactory;
use Psy\Shell;

/**
 * A runkit-based code reloader, which is pretty much magic.
 */
class Reloader implements Listener
{
    private $parser;
    private $timestamps = array();

    /**
     * Only enabled if Runkit is installed.
     *
     * @return bool
     */
    public static function isSupported()
    {
        return extension_loaded('runkit');
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $parserFactory = new ParserFactory();
        $this->parser = $parserFactory->createParser();
    }

    /**
     * No-op.
     */
    public function beforeLoop(Shell $shell)
    {
    }

    /**
     * Reload code on input.
     */
    public function onInput(Shell $shell, $input)
    {
        $this->reload($shell);
    }

    /**
     * No-op.
     */
    public function onExecute(Shell $shell, $command)
    {
    }

    /**
     * No-op.
     */
    public function afterLoop(Shell $shell)
    {
    }

    /**
     * Look through included files and update anything with a new timestamp.
     */
    private function reload(Shell $shell)
    {
        clearstatcache();
        $modified = array();

        foreach (get_included_files() as $file) {
            $timestamp = filemtime($file);

            if (!isset($this->timestamps[$file])) {
                $this->timestamps[$file] = $timestamp;
                continue;
            }

            if ($this->timestamps[$file] === $timestamp) {
                continue;
            }

            if (!$this->lintFile($file)) {
                $msg = sprintf('Modified file "%s" could not be reloaded', $file);
                $shell->writeException(new ParseErrorException($msg));
                continue;
            }

            $modified[] = $file;
            $this->timestamps[$file] = $timestamp;
        }

        // switch (count($modified)) {
        //     case 0:
        //         return;

        //     case 1:
        //         printf("Reloading modified file: \"%s\"\n", str_replace(getcwd(), '.', $file));
        //         break;

        //     default:
        //         printf("Reloading %d modified files\n", count($modified));
        //         break;
        // }

        foreach ($modified as $file) {
            runkit_import($file, (
                RUNKIT_IMPORT_FUNCTIONS |
                RUNKIT_IMPORT_CLASSES |
                RUNKIT_IMPORT_CLASS_METHODS |
                RUNKIT_IMPORT_CLASS_CONSTS |
                RUNKIT_IMPORT_CLASS_PROPS |
                RUNKIT_IMPORT_OVERRIDE
            ));
        }
    }

    /**
     * Should this file be re-imported?
     *
     * Use PHP-Parser to ensure that the file is valid PHP, then run
     * `runkit_lint_file` over the file if it exists.
     *
     * @param string $file
     *
     * @return bool
     */
    private function lintFile($file)
    {
        // first try to parse it
        try {
            $this->parser->parse(file_get_contents($file));
        } catch (\Exception $e) {
            return false;
        }

        // apparently this isn't always a thing, so bail early if it isn't
        if (!function_exists('runkit_lint_file')) {
            return true;
        }

        return runkit_lint_file($file);
    }
}
