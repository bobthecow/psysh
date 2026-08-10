<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures;

final class HttpsStream
{
    /** @var array<string, string> */
    private static array $responses = [];

    /** @var resource|null */
    public $context;

    private string $response = '';
    private int $position = 0;

    /**
     * @param array<string, string> $responses
     */
    public static function register(array $responses): void
    {
        \stream_wrapper_unregister('https');
        \stream_wrapper_register('https', self::class);
        self::$responses = $responses;
    }

    public static function restore(): void
    {
        self::$responses = [];
        \stream_wrapper_unregister('https');
        \stream_wrapper_restore('https');
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (!isset(self::$responses[$path])) {
            return false;
        }

        $this->response = self::$responses[$path];
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = \substr($this->response, $this->position, $count);
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen($this->response);
    }

    public function stream_stat(): array
    {
        return [];
    }
}
