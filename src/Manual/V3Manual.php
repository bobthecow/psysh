<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Manual;

use Psy\Exception\InvalidManualException;

/**
 * V3 manual format loader.
 *
 * Loads structured manual documentation from a single pre-built PHP file.
 *
 * The PHP file returns an object with a get($id) method that handles the data loading internally.
 */
class V3Manual implements ManualInterface
{
    /** @var object */
    private $data;

    /** @var array<string, object> */
    private static $cache = [];

    // TODO: Remove the cache and duplicate __COMPILER_HALT_OFFSET__ handling
    // after dropping support for PHP 8.2.

    /**
     * Constructor.
     *
     * @param string $filePath Path to the PHP manual file
     *
     * @throws InvalidManualException if file doesn't return a valid manual data object
     */
    public function __construct(string $filePath)
    {
        if (!isset(self::$cache[$filePath])) {
            self::$cache[$filePath] = self::load($filePath);
        }

        $this->data = self::$cache[$filePath];
    }

    /**
     * Reload a cached manual from disk.
     */
    public static function reload(string $filePath): self
    {
        \clearstatcache(true, $filePath);
        if (\function_exists('opcache_invalidate')) {
            @\opcache_invalidate($filePath, true);
        }

        $data = self::load($filePath, isset(self::$cache[$filePath]));
        self::$cache[$filePath] = $data;

        return new self($filePath);
    }

    /**
     * Load and validate a manual data object.
     */
    private static function load(string $filePath, bool $reload = false): object
    {
        // Suppress output from invalid/corrupted manual files
        $data = null;
        \ob_start();
        try {
            if ($reload && \PHP_VERSION_ID < 80300) {
                // Before PHP 8.3, requiring the same file again emits a
                // harmless warning while re-registering its filename-scoped
                // __COMPILER_HALT_OFFSET__ constant. The offset used by the
                // manual itself is still compiled into the new code.
                $data = self::requireReloadedManual($filePath);
            } else {
                $data = require $filePath;
            }
        } finally {
            \ob_end_clean();
        }

        // Validate that the file returned an object with the expected interface
        if (!\is_object($data)) {
            throw new InvalidManualException(\sprintf('Manual file "%s" must return an object, got %s', $filePath, \gettype($data)), $filePath);
        }

        foreach (['get', 'getMeta'] as $method) {
            if (!\method_exists($data, $method)) {
                throw new InvalidManualException(\sprintf('Manual data object must have a %s() method', $method), $filePath);
            }
        }

        // Verify the manual format version is v3.x
        $meta = $data->getMeta();
        if (!isset($meta['version']) || !\preg_match('/^3\./', (string) $meta['version'])) {
            $version = $meta['version'] ?? 'unknown';
            throw new InvalidManualException(\sprintf('Manual file "%s" must be v3.x format, got version %s', $filePath, $version), $filePath);
        }

        return $data;
    }

    /**
     * Require a previously loaded manual while suppressing PHP's duplicate
     * __COMPILER_HALT_OFFSET__ warning.
     *
     * @return mixed
     */
    private static function requireReloadedManual(string $filePath)
    {
        $realPath = \realpath($filePath);
        $haltOffsetSeverity = \PHP_VERSION_ID < 80000 ? \E_NOTICE : \E_WARNING;
        $previousErrorHandler = null;
        $previousErrorHandler = \set_error_handler(static function (...$error) use ($realPath, $haltOffsetSeverity, &$previousErrorHandler) {
            if ($error[0] === $haltOffsetSeverity && $error[1] === 'Constant  already defined' && \realpath($error[2]) === $realPath) {
                return true;
            }

            return \is_callable($previousErrorHandler) ? $previousErrorHandler(...$error) : false;
        });

        try {
            return require $filePath;
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        return $this->data->get($id);
    }

    /**
     * Get all available manual IDs when supported by the loaded manual file.
     *
     * @return string[]
     */
    public function getIds(): array
    {
        if (!\method_exists($this->data, 'getIds')) {
            return [];
        }

        return $this->data->getIds();
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): int
    {
        return 3;
    }

    /**
     * Get manual metadata (version, language, build date, etc).
     *
     * @return array
     */
    public function getMeta(): array
    {
        return $this->data->getMeta();
    }
}
