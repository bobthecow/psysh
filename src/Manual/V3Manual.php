<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Manual;

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

    /**
     * Constructor.
     *
     * @param string $filePath Path to the PHP manual file
     *
     * @throws \InvalidArgumentException if file doesn't return a valid manual data object
     */
    public function __construct(string $filePath)
    {
        // Avoid redefining __COMPILER_HALT_OFFSET__
        // TODO: Remove cache after dropping support for PHP 8.2.
        if (isset(self::$cache[$filePath])) {
            $data = self::$cache[$filePath];
        } else {
            $data = require $filePath;
            self::$cache[$filePath] = $data;
        }

        // Validate that the file returned an object with the expected interface
        if (!\is_object($data)) {
            throw new \InvalidArgumentException(\sprintf('Manual file "%s" must return an object, got %s', $filePath, \gettype($data)));
        }

        $requiredMethods = ['get', 'getMeta'];
        foreach ($requiredMethods as $method) {
            if (!\method_exists($data, $method)) {
                throw new \InvalidArgumentException(\sprintf('Manual data object must have a %s() method', $method));
            }
        }

        // Verify the manual format version is v3.x
        $meta = $data->getMeta();
        if (!isset($meta['version']) || !\preg_match('/^3\./', (string) $meta['version'])) {
            $version = $meta['version'] ?? 'unknown';
            throw new \InvalidArgumentException(\sprintf('Manual file "%s" must be v3.x format, got version %s', $filePath, $version));
        }

        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        return $this->data->get($id);
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
