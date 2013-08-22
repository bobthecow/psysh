<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use Psy\Shell;
use Symfony\Component\Finder\Finder;

/**
 * A Psy Shell Phar compiler.
 */
class Compiler
{
    /**
     * Compiles psysh into a single phar file
     *
     * @param string $pharFile The full path to the file to create
     */
    public function compile($pharFile = 'psysh.phar')
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $this->version = Shell::VERSION;

        $phar = new \Phar($pharFile, 0, 'psysh.phar');
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('Compiler.php')
            ->notName('Autoloader.php')
            ->in(__DIR__.'/..');

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->exclude('Tests')
            ->in(__DIR__.'/../../vendor/symfony/')
            ->in(__DIR__.'/../../vendor/nikic/');

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/autoload.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/composer/include_paths.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/composer/autoload_real.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/composer/autoload_namespaces.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/composer/autoload_classmap.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/composer/ClassLoader.php'));
        $this->addPsyshBin($phar);

        // Stubs
        $phar->setStub($this->getStub());

        $phar->stopBuffering();

        // $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../LICENSE'), false);

        unset($phar);
    }

    /**
     * Add a file to the psysh Phar.
     *
     * @param Phar        $phar
     * @param SplFileInfo $file
     * @param bool        $strip (default: true)
     */
    private function addFile($phar, $file, $strip = true)
    {
        $path = str_replace(dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR, '', $file->getRealPath());

        $content = file_get_contents($file);
        if ($strip) {
            $content = $this->stripWhitespace($content);
        } elseif ('LICENSE' === basename($file)) {
            $content = "\n".$content."\n";
        }

        $phar->addFromString($path, $content);
    }

    /**
     * Add the psysh bin file.
     *
     * @param Phar $phar
     */
    private function addPsyshBin($phar)
    {
        $content = file_get_contents(__DIR__.'/../../bin/psysh');
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString('bin/psysh', $content);
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param string $source A PHP string
     *
     * @return string The PHP string with the whitespace removed
     */
    private function stripWhitespace($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }

    /**
     * Get a Phar stub for psysh
     *
     * @return string
     */
    private function getStub()
    {
        return <<<'EOS'
#!/usr/bin/env php
<?php

Phar::mapPhar('psysh.phar');

// Allow running phar directly, or including.
if ('cli' === php_sapi_name() && in_array(basename(__FILE__), array(basename($_SERVER['argv'][0]), basename(realpath($_SERVER['argv'][0]))))) {
    require 'phar://psysh.phar/bin/psysh';
} else {
    require 'phar://psysh.phar/vendor/autoload.php';
}

__HALT_COMPILER();
EOS;
    }
}
