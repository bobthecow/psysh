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

use Psy\Util\Str;

/**
 * Command history manager.
 *
 * Manages a list of previously entered commands with navigation support.
 */
class History
{
    private const HISTORY_SIGNATURE = [
        'type'    => 'psysh-history',
        'format'  => 'jsonl',
        'version' => 1,
    ];

    /** @var array[] History entries (index 0 = oldest, last index = most recent) */
    private array $entries = [];

    /** @var int Current position in history (-1 = not in history, index entries in when navigating) */
    private int $position = -1;

    /** @var string|null Temporary entry saved when entering history */
    private ?string $temporaryEntry = null;

    private int $maxSize;
    private bool $eraseDups;

    public function __construct(int $maxSize = 10000, bool $eraseDups = false)
    {
        $this->maxSize = $maxSize;
        $this->eraseDups = $eraseDups;
    }

    /**
     * Add an entry to history.
     *
     * Skips empty lines and duplicates of the most recent entry.
     * If eraseDups is enabled, removes any previous occurrence of the same command.
     */
    public function add(string $line): void
    {
        if (\trim($line) === '') {
            return;
        }

        $lastIndex = \count($this->entries) - 1;
        if ($lastIndex >= 0 && $this->entries[$lastIndex]['command'] === $line) {
            return;
        }

        if ($this->eraseDups) {
            $this->entries = \array_values(\array_filter($this->entries, fn ($entry) => $entry['command'] !== $line));
        }

        $this->entries[] = [
            'command'   => $line,
            'timestamp' => \time(),
            'lines'     => \substr_count($line, "\n") + 1,
        ];

        if ($this->maxSize > 0 && \count($this->entries) > $this->maxSize) {
            $this->entries = \array_slice($this->entries, -$this->maxSize);
        }
    }

    /**
     * Get the previous history entry.
     *
     * Moves backward in history (toward older entries).
     *
     * @return string|null The previous entry, or null if at the beginning
     */
    public function getPrevious(): ?string
    {
        if (empty($this->entries)) {
            return null;
        }

        $entryCount = \count($this->entries);

        if ($this->position === -1) {
            $this->position = $entryCount - 1;

            return $this->entries[$this->position]['command'];
        }

        if ($this->position > 0) {
            $this->position--;

            return $this->entries[$this->position]['command'];
        }

        return null;
    }

    /**
     * Get the next history entry.
     *
     * Moves forward in history (toward newer entries).
     *
     * @return string|null The next entry, null if at the end, or empty string if returning to current input
     */
    public function getNext(): ?string
    {
        if ($this->position === -1) {
            return null;
        }

        $lastIndex = \count($this->entries) - 1;

        if ($this->position < $lastIndex) {
            $this->position++;

            return $this->entries[$this->position]['command'];
        }

        if ($this->position === $lastIndex) {
            $this->position = -1;
            $temp = $this->temporaryEntry;
            $this->temporaryEntry = null;

            return $temp ?? '';
        }

        return null;
    }

    /**
     * Reset history navigation.
     *
     * Call this when a line is accepted to prepare for next input.
     */
    public function reset(): void
    {
        $this->position = -1;
        $this->temporaryEntry = null;
    }

    /**
     * Save the current input as temporary entry for restoring later.
     */
    public function saveTemporaryEntry(string $entry): void
    {
        $this->temporaryEntry = $entry;
    }

    /**
     * Check if currently navigating history.
     */
    public function isInHistory(): bool
    {
        return $this->position !== -1;
    }

    /**
     * Get the current position in history.
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Get all history entries.
     *
     * @return array[] History entries (index 0 = oldest, last index = most recent)
     */
    public function getAll(): array
    {
        return $this->entries;
    }

    /**
     * Search history for entries matching a query.
     *
     * @param string $query   The search query (case-insensitive substring search)
     * @param bool   $reverse If true, return oldest matches first; if false, newest first
     *
     * @return string[] Matching command strings
     */
    public function search(string $query, bool $reverse = false): array
    {
        if ($query === '') {
            $commands = \array_column($this->entries, 'command');

            return $reverse ? $commands : \array_reverse($commands);
        }

        $matches = [];
        foreach ($this->entries as $entry) {
            if (\stripos($entry['command'], $query) !== false) {
                $matches[] = $entry['command'];
            }
        }

        return $reverse ? $matches : \array_reverse($matches);
    }

    /**
     * Get the number of entries in history.
     */
    public function getCount(): int
    {
        return \count($this->entries);
    }

    /**
     * Clear all history.
     */
    public function clear(): void
    {
        $this->entries = [];
        $this->position = -1;
        $this->temporaryEntry = null;
    }

    /**
     * Load history from file.
     *
     * Loads JSONL (interactive history format) entries.
     * Optionally accepts a JSON signature line at the top of the file.
     * Non-JSON lines are ignored.
     */
    public function loadFromFile(string $path): void
    {
        $lines = $this->readLinesFromFile($path);
        if ($lines === null) {
            return;
        }

        $hasJsonSignature = false;
        $entries = [];
        foreach ($lines as $i => $line) {
            if ($i === 0 && self::isJsonHistorySignatureLine($line)) {
                $hasJsonSignature = true;

                continue;
            }

            $entry = $this->parseJsonLine($line);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        // If the file has content but no valid JSONL entries, leave history unchanged.
        if (!empty($lines) && empty($entries) && !$hasJsonSignature) {
            return;
        }

        $this->setEntries($entries);
    }

    /**
     * Import history from a legacy plain-text file.
     *
     * Legacy format stores one command per line.
     */
    public function importFromFile(string $path): void
    {
        $lines = $this->readLinesFromFile($path);
        if ($lines === null) {
            return;
        }

        // libedit legacy files may start with a version signature line.
        if (!empty($lines) && $lines[0] === '_HiStOrY_V2_') {
            \array_shift($lines);
        }

        $entries = [];
        foreach ($lines as $line) {
            $entry = $this->parseLegacyLine($line);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        $this->setEntries($entries);
    }

    /**
     * Save history to file.
     *
     * Saves in JSONL format with metadata.
     */
    public function saveToFile(string $path): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0700, true);
        }

        $jsonFlags = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;
        $lines = \array_map(fn ($entry) => \json_encode($entry, $jsonFlags), $this->entries);
        \array_unshift($lines, \json_encode(self::HISTORY_SIGNATURE, $jsonFlags));

        $content = \implode("\n", $lines)."\n";

        @\file_put_contents($path, $content);
    }

    /**
     * Check whether a line is valid JSON history (entry or signature).
     */
    public static function isJsonHistoryLine(string $line): bool
    {
        $data = @\json_decode(\trim($line), true);
        if (!\is_array($data)) {
            return false;
        }

        if (isset($data['command']) && \is_string($data['command'])) {
            return true;
        }

        return self::isJsonHistorySignatureData($data);
    }

    /**
     * Get display command for history navigation.
     *
     * For multi-line commands, returns a single-line representation.
     * Used by the `hist` command for display purposes.
     *
     * @param array $entry History entry
     */
    public function getDisplayCommand(array $entry): string
    {
        $command = $entry['command'];
        $lines = $entry['lines'] ?? 1;

        if ($lines === 1) {
            return $command;
        }

        $commandLines = \explode("\n", $command);
        $commandLines = \array_map('trim', $commandLines);
        $commandLines = \array_filter($commandLines); // Remove empty lines

        return \implode(' ', $commandLines);
    }

    /**
     * Parse a JSONL line into a history entry.
     *
     * @return array|null
     */
    private function parseJsonLine(string $line): ?array
    {
        $data = @\json_decode(\trim($line), true);

        if (!\is_array($data) || !isset($data['command']) || !\is_string($data['command'])) {
            return null;
        }

        return [
            'command'   => $data['command'],
            'timestamp' => $data['timestamp'] ?? \time(),
            'lines'     => $data['lines'] ?? (\substr_count($data['command'], "\n") + 1),
        ];
    }

    /**
     * Parse a legacy plain-text history line into an entry.
     */
    private function parseLegacyLine(string $line): ?array
    {
        // GNU/libedit history comments and timestamps use NUL-prefixed lines.
        if ($line === '' || $line[0] === "\0") {
            return null;
        }

        // Ignore comment/timestamp suffix after NUL.
        if (($pos = \strpos($line, "\0")) !== false) {
            $line = \substr($line, 0, $pos);
        }

        $command = Str::unvis(\rtrim($line, "\r"));
        if ($command === '') {
            return null;
        }

        return [
            'command'   => $command,
            'timestamp' => \time(),
            'lines'     => \substr_count($command, "\n") + 1,
        ];
    }

    /**
     * Check whether a line is a JSON history signature.
     */
    private static function isJsonHistorySignatureLine(string $line): bool
    {
        $data = @\json_decode(\trim($line), true);

        return \is_array($data) && self::isJsonHistorySignatureData($data);
    }

    /**
     * Check whether decoded JSON matches the history signature schema.
     *
     * @param array $data
     */
    private static function isJsonHistorySignatureData(array $data): bool
    {
        return ($data['type'] ?? null) === self::HISTORY_SIGNATURE['type']
            && ($data['format'] ?? null) === self::HISTORY_SIGNATURE['format']
            && \is_int($data['version'] ?? null)
            && $data['version'] === self::HISTORY_SIGNATURE['version'];
    }

    /**
     * Read and normalize non-empty lines from a file.
     *
     * @return string[]|null
     */
    private function readLinesFromFile(string $path): ?array
    {
        if (!\file_exists($path) || !\is_readable($path)) {
            return null;
        }

        $content = @\file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $lines = \explode("\n", $content);

        return \array_values(\array_filter($lines, fn ($line) => \trim($line) !== ''));
    }

    /**
     * Replace history entries, enforcing the configured max size.
     *
     * @param array $entries
     */
    private function setEntries(array $entries): void
    {
        if ($this->maxSize > 0 && \count($entries) > $this->maxSize) {
            $entries = \array_slice($entries, -$this->maxSize);
        }

        $this->entries = \array_values($entries);
    }
}
