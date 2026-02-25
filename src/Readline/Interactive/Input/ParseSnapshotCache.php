<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Input;

use PhpParser\Error;
use PhpParser\Parser;
use Psy\ParserFactory;

/**
 * Maintains a cached parse snapshot for buffer text.
 *
 * Uses a small LRU cache (2 entries) to avoid thrashing when callers
 * alternate between full-buffer and partial (before-cursor) text.
 */
class ParseSnapshotCache
{
    private const CACHE_SIZE = 2;

    private Parser $parser;

    /**
     * LRU cache of recent snapshots, most-recently-used first.
     *
     * @var array{code: string, snapshot: ParseSnapshot}[]
     */
    private array $cache = [];

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->createParser();
    }

    /**
     * Get the parse snapshot for the given code, using cached data when possible.
     */
    public function getSnapshot(string $code): ParseSnapshot
    {
        foreach ($this->cache as $i => $entry) {
            if ($entry['code'] === $code) {
                // Promote to front (most-recently-used)
                if ($i > 0) {
                    \array_splice($this->cache, $i, 1);
                    \array_unshift($this->cache, $entry);
                }

                return $entry['snapshot'];
            }
        }

        $snapshot = $this->buildSnapshot($code);

        // Prepend new entry and trim to cache size
        \array_unshift($this->cache, ['code' => $code, 'snapshot' => $snapshot]);
        if (\count($this->cache) > self::CACHE_SIZE) {
            \array_pop($this->cache);
        }

        return $snapshot;
    }

    /**
     * Check whether appending a semicolon would make the code parseable.
     */
    public function canBeFixedWithSemicolon(string $code): bool
    {
        try {
            $this->parser->parse('<?php '.$code.";\n");

            return true;
        } catch (Error $e) {
            return false;
        }
    }

    private function buildSnapshot(string $code): ParseSnapshot
    {
        $tokens = @\token_get_all('<?php '.$code);

        $tokenPositions = [];
        $position = 0;
        foreach ($tokens as $index => $token) {
            $text = \is_array($token) ? $token[1] : $token;
            $length = \mb_strlen($text);
            $tokenPositions[$index] = ['start' => $position, 'end' => $position + $length];
            $position += $length;
        }

        $ast = null;
        $lastError = null;
        try {
            // Trailing newline improves heredoc EOF behavior to match runtime checks.
            $ast = $this->parser->parse('<?php '.$code."\n");
        } catch (Error $e) {
            $lastError = $e;
        }

        return new ParseSnapshot($tokens, $tokenPositions, $ast, $lastError);
    }
}
