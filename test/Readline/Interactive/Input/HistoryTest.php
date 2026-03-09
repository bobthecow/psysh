<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Input;

use Psy\Readline\Interactive\Input\History;
use Psy\Test\TestCase;

/**
 * @covers \Psy\Readline\Interactive\Input\History
 */
class HistoryTest extends TestCase
{
    private const JSON_HISTORY_SIGNATURE = [
        'type'    => 'psysh-history',
        'format'  => 'jsonl',
        'version' => 1,
    ];

    public function testConstruct()
    {
        $history = new History();
        $this->assertSame(0, $history->getCount());
        $this->assertSame(-1, $history->getPosition());
        $this->assertFalse($history->isInHistory());
    }

    public function testConstructWithMaxSize()
    {
        $history = new History(100);
        $this->assertSame(0, $history->getCount());
    }

    public function testAddEntry()
    {
        $history = new History();
        $history->add('first command');

        $this->assertSame(1, $history->getCount());

        $entries = $history->getAll();
        $this->assertCount(1, $entries);
        $this->assertSame('first command', $entries[0]['command']);
        $this->assertSame(1, $entries[0]['lines']);
        $this->assertArrayHasKey('timestamp', $entries[0]);
    }

    public function testAddMultiLineEntry()
    {
        $history = new History();
        $history->add("function foo() {\n    return 42;\n}");

        $entries = $history->getAll();
        $this->assertSame("function foo() {\n    return 42;\n}", $entries[0]['command']);
        $this->assertSame(3, $entries[0]['lines']);
    }

    public function testAddMultipleEntries()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');
        $history->add('third');

        $this->assertSame(3, $history->getCount());

        $entries = $history->getAll();
        // Oldest first
        $this->assertSame('first', $entries[0]['command']);
        $this->assertSame('second', $entries[1]['command']);
        $this->assertSame('third', $entries[2]['command']);
    }

    public function testSkipEmptyLines()
    {
        $history = new History();
        $history->add('command');
        $history->add('');
        $history->add('   ');
        $history->add("\t");

        $this->assertSame(1, $history->getCount());
        $entries = $history->getAll();
        $this->assertSame('command', $entries[0]['command']);
    }

    public function testSkipDuplicates()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');
        $history->add('second'); // Duplicate of most recent
        $history->add('third');
        $history->add('third'); // Duplicate of most recent

        $this->assertSame(3, $history->getCount());

        $entries = $history->getAll();
        $this->assertSame('first', $entries[0]['command']);
        $this->assertSame('second', $entries[1]['command']);
        $this->assertSame('third', $entries[2]['command']);
    }

    public function testGetPreviousFromStart()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');
        $history->add('third');

        // Get most recent
        $this->assertSame('third', $history->getPrevious());
        $this->assertSame(2, $history->getPosition());
        $this->assertTrue($history->isInHistory());
    }

    public function testGetPreviousMultipleTimes()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');
        $history->add('third');

        $this->assertSame('third', $history->getPrevious());
        $this->assertSame('second', $history->getPrevious());
        $this->assertSame('first', $history->getPrevious());
        $this->assertSame(0, $history->getPosition());
    }

    public function testGetPreviousAtEnd()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');

        $history->getPrevious(); // second
        $history->getPrevious(); // first
        $this->assertNull($history->getPrevious()); // Can't go further
        $this->assertSame(0, $history->getPosition()); // Position unchanged
    }

    public function testGetPreviousEmptyHistory()
    {
        $history = new History();
        $this->assertNull($history->getPrevious());
        $this->assertSame(-1, $history->getPosition());
    }

    public function testGetNextFromHistory()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');
        $history->add('third');

        // Navigate backward
        $history->getPrevious(); // third
        $history->getPrevious(); // second

        // Navigate forward
        $this->assertSame('third', $history->getNext());
        $this->assertSame(2, $history->getPosition());
    }

    public function testGetNextExitsHistory()
    {
        $history = new History();
        $history->add('command');

        // Navigate backward
        $history->getPrevious();

        // Forward past most recent, exits history
        $this->assertSame('', $history->getNext());
        $this->assertSame(-1, $history->getPosition());
        $this->assertFalse($history->isInHistory());
    }

    public function testGetNextWhenNotInHistory()
    {
        $history = new History();
        $history->add('command');

        $this->assertNull($history->getNext());
        $this->assertSame(-1, $history->getPosition());
    }

    public function testReset()
    {
        $history = new History();
        $history->add('command');

        // Navigate into history
        $history->getPrevious();

        // Reset
        $history->reset();

        $this->assertSame(-1, $history->getPosition());
        $this->assertFalse($history->isInHistory());
    }

    public function testClear()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');
        $history->getPrevious();

        $history->clear();

        $this->assertSame(0, $history->getCount());
        $this->assertSame([], $history->getAll());
        $this->assertSame(-1, $history->getPosition());
    }

    public function testClearResetsLastNavigatedCommand()
    {
        $history = new History();
        $history->add('repeat');

        $this->assertSame('repeat', $history->getPrevious());

        $history->clear();
        $history->add('repeat');

        $this->assertSame('repeat', $history->getPrevious());
    }

    public function testLoadFromFileResetsNavigationState()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');
        \file_put_contents($tempFile, \json_encode(['command' => 'repeat', 'timestamp' => 1000, 'lines' => 1])."\n");

        $history = new History();
        $history->add('repeat');
        $this->assertSame('repeat', $history->getPrevious());

        $history->loadFromFile($tempFile);

        $this->assertSame(-1, $history->getPosition());
        $this->assertSame('repeat', $history->getPrevious());

        @\unlink($tempFile);
    }

    public function testMaxSize()
    {
        $history = new History(3);
        $history->add('first');
        $history->add('second');
        $history->add('third');
        $history->add('fourth');

        // Should keep only 3 most recent
        $this->assertSame(3, $history->getCount());

        $entries = $history->getAll();
        $this->assertSame('second', $entries[0]['command']);
        $this->assertSame('third', $entries[1]['command']);
        $this->assertSame('fourth', $entries[2]['command']);
    }

    public function testLoadFromJsonlFile()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');

        // Write JSONL history file (most recent last)
        $lines = [
            \json_encode(['command' => 'first', 'timestamp' => 1000, 'lines' => 1]),
            \json_encode(['command' => 'second', 'timestamp' => 2000, 'lines' => 1]),
            \json_encode(['command' => 'third', 'timestamp' => 3000, 'lines' => 1]),
        ];
        \file_put_contents($tempFile, \implode("\n", $lines)."\n");

        $history = new History();
        $history->loadFromFile($tempFile);

        // Should load in file order (oldest first internally)
        $this->assertSame(3, $history->getCount());

        $entries = $history->getAll();
        $this->assertSame('first', $entries[0]['command']);
        $this->assertSame(1000, $entries[0]['timestamp']);
        $this->assertSame('second', $entries[1]['command']);
        $this->assertSame(2000, $entries[1]['timestamp']);
        $this->assertSame('third', $entries[2]['command']);
        $this->assertSame(3000, $entries[2]['timestamp']);

        @\unlink($tempFile);
    }

    public function testLoadFromJsonlFileWithSignature()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');

        $lines = [
            \json_encode(self::JSON_HISTORY_SIGNATURE),
            \json_encode(['command' => 'first', 'timestamp' => 1000, 'lines' => 1]),
            \json_encode(['command' => 'second', 'timestamp' => 2000, 'lines' => 1]),
        ];
        \file_put_contents($tempFile, \implode("\n", $lines)."\n");

        $history = new History();
        $history->loadFromFile($tempFile);

        $this->assertSame(2, $history->getCount());
        $entries = $history->getAll();
        $this->assertSame('first', $entries[0]['command']);
        $this->assertSame('second', $entries[1]['command']);

        @\unlink($tempFile);
    }

    public function testImportFromLegacyPlainTextFile()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');

        // Legacy readline history format is plain text, one command per line.
        \file_put_contents($tempFile, "first command\nsecond command\nthird command\n");

        $history = new History();
        $history->importFromFile($tempFile);

        $this->assertSame(3, $history->getCount());
        $entries = $history->getAll();
        $this->assertSame('first command', $entries[0]['command']);
        $this->assertSame('second command', $entries[1]['command']);
        $this->assertSame('third command', $entries[2]['command']);

        @\unlink($tempFile);
    }

    public function testImportFromLegacyPlainTextFileSkipsLibeditSignature()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');
        \file_put_contents($tempFile, "_HiStOrY_V2_\nfirst command\nsecond command\n");

        $history = new History();
        $history->importFromFile($tempFile);

        $this->assertSame(2, $history->getCount());
        $entries = $history->getAll();
        $this->assertSame('first command', $entries[0]['command']);
        $this->assertSame('second command', $entries[1]['command']);

        @\unlink($tempFile);
    }

    public function testImportFromLegacyPlainTextFileDecodesEscapedNewlines()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');
        \file_put_contents($tempFile, "echo \"a\"\\necho \"b\"\n");

        $history = new History();
        $history->importFromFile($tempFile);

        $this->assertSame(1, $history->getCount());
        $entries = $history->getAll();
        $this->assertSame("echo \"a\"\necho \"b\"", $entries[0]['command']);
        $this->assertSame(2, $entries[0]['lines']);

        @\unlink($tempFile);
    }

    public function testImportFromLegacyPlainTextFileSkipsNullCommentsAndTimestamps()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');
        \file_put_contents($tempFile, "_HiStOrY_V2_\n\0timestamp\nvalid\0comment\n");

        $history = new History();
        $history->importFromFile($tempFile);

        $this->assertSame(1, $history->getCount());
        $entries = $history->getAll();
        $this->assertSame('valid', $entries[0]['command']);

        @\unlink($tempFile);
    }

    public function testImportFromGnuLegacyFixture()
    {
        $history = new History();
        $history->importFromFile($this->fixturePath('Readline/gnu_history_legacy'));

        $entries = $history->getAll();
        $commands = \array_map(fn ($entry) => $entry['command'], $entries);
        $lineCounts = \array_map(fn ($entry) => $entry['lines'], $entries);

        $this->assertSame([
            'plain command',
            'echo gnu space',
            "echo first\necho second",
        ], $commands);
        $this->assertSame([1, 1, 2], $lineCounts);
    }

    public function testImportFromLibeditLegacyFixture()
    {
        $history = new History();
        $history->importFromFile($this->fixturePath('Readline/libedit_history_legacy'));

        $entries = $history->getAll();
        $commands = \array_map(fn ($entry) => $entry['command'], $entries);
        $lineCounts = \array_map(fn ($entry) => $entry['lines'], $entries);

        $this->assertSame([
            'simple command',
            'echo space',
            "echo line1\necho line2",
            'visible',
        ], $commands);
        $this->assertSame([1, 1, 2, 1], $lineCounts);
    }

    public function testLoadFromLegacyPlainTextFileDoesNotImport()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');
        \file_put_contents($tempFile, "first command\nsecond command\n");

        $history = new History();
        $history->add('existing');
        $history->loadFromFile($tempFile);

        $this->assertSame(1, $history->getCount());
        $entries = $history->getAll();
        $this->assertSame('existing', $entries[0]['command']);

        @\unlink($tempFile);
    }

    public function testLoadFromJsonlFileWithMultiLine()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');

        $multiLineCmd = "function foo() {\n    return 42;\n}";
        $lines = [
            \json_encode(['command' => 'echo "hello"', 'timestamp' => 1000, 'lines' => 1]),
            \json_encode(['command' => $multiLineCmd, 'timestamp' => 2000, 'lines' => 3]),
        ];
        \file_put_contents($tempFile, \implode("\n", $lines)."\n");

        $history = new History();
        $history->loadFromFile($tempFile);

        $entries = $history->getAll();
        $this->assertSame('echo "hello"', $entries[0]['command']);
        $this->assertSame($multiLineCmd, $entries[1]['command']);
        $this->assertSame(3, $entries[1]['lines']);

        @\unlink($tempFile);
    }

    public function testLoadFromJsonlFileSkipsEmptyLines()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');

        // Write JSONL file with empty lines
        $lines = [
            \json_encode(['command' => 'first', 'timestamp' => 1000, 'lines' => 1]),
            '',
            \json_encode(['command' => 'second', 'timestamp' => 2000, 'lines' => 1]),
            '   ',
            \json_encode(['command' => 'third', 'timestamp' => 3000, 'lines' => 1]),
        ];
        \file_put_contents($tempFile, \implode("\n", $lines)."\n");

        $history = new History();
        $history->loadFromFile($tempFile);

        $this->assertSame(3, $history->getCount());

        $entries = $history->getAll();
        $this->assertSame('first', $entries[0]['command']);
        $this->assertSame('second', $entries[1]['command']);
        $this->assertSame('third', $entries[2]['command']);

        @\unlink($tempFile);
    }

    public function testLoadFromFileRespectsMaxSize()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');

        // Write more entries than max size
        $lines = [
            \json_encode(['command' => 'first', 'timestamp' => 1000, 'lines' => 1]),
            \json_encode(['command' => 'second', 'timestamp' => 2000, 'lines' => 1]),
            \json_encode(['command' => 'third', 'timestamp' => 3000, 'lines' => 1]),
            \json_encode(['command' => 'fourth', 'timestamp' => 4000, 'lines' => 1]),
        ];
        \file_put_contents($tempFile, \implode("\n", $lines)."\n");

        $history = new History(2);
        $history->loadFromFile($tempFile);

        // Should keep only 2 most recent
        $this->assertSame(2, $history->getCount());

        $entries = $history->getAll();
        $this->assertSame('third', $entries[0]['command']);
        $this->assertSame('fourth', $entries[1]['command']);

        @\unlink($tempFile);
    }

    public function testLoadFromNonExistentFile()
    {
        $history = new History();
        $history->add('existing');

        // Should not error
        $history->loadFromFile('/nonexistent/path/to/file');

        // Should preserve existing entries
        $this->assertSame(1, $history->getCount());
    }

    public function testLoadFromInvalidJsonl()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');

        // Write file with mix of valid and invalid JSON
        $lines = [
            \json_encode(['command' => 'good1', 'timestamp' => 1000, 'lines' => 1]),
            'THIS IS NOT JSON',
            \json_encode(['command' => 'good2', 'timestamp' => 2000, 'lines' => 1]),
            '{"incomplete": "json"',
            \json_encode(['command' => 'good3', 'timestamp' => 3000, 'lines' => 1]),
        ];
        \file_put_contents($tempFile, \implode("\n", $lines)."\n");

        $history = new History();
        $history->loadFromFile($tempFile);

        // Should only load valid entries
        $this->assertSame(3, $history->getCount());

        $entries = $history->getAll();
        $this->assertSame('good1', $entries[0]['command']);
        $this->assertSame('good2', $entries[1]['command']);
        $this->assertSame('good3', $entries[2]['command']);

        @\unlink($tempFile);
    }

    public function testSaveToJsonlFile()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');
        @\unlink($tempFile); // Remove the temp file so we can test creation

        $history = new History();
        $history->add('first');
        $history->add('second');
        $history->add('third');

        $history->saveToFile($tempFile);

        // Read file and verify JSONL format (most recent last)
        $content = \file_get_contents($tempFile);
        $lines = \explode("\n", \trim($content));

        $this->assertCount(4, $lines);

        $signature = \json_decode($lines[0], true);
        $this->assertSame(self::JSON_HISTORY_SIGNATURE, $signature);

        $entry1 = \json_decode($lines[1], true);
        $this->assertSame('first', $entry1['command']);
        $this->assertArrayHasKey('timestamp', $entry1);
        $this->assertSame(1, $entry1['lines']);

        $entry2 = \json_decode($lines[2], true);
        $this->assertSame('second', $entry2['command']);

        $entry3 = \json_decode($lines[3], true);
        $this->assertSame('third', $entry3['command']);

        @\unlink($tempFile);
    }

    public function testSaveToFileWithMultiLine()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');
        @\unlink($tempFile);

        $history = new History();
        $history->add('echo "hello"');
        $history->add("function foo() {\n    return 42;\n}");

        $history->saveToFile($tempFile);

        $content = \file_get_contents($tempFile);
        $lines = \explode("\n", \trim($content));

        $signature = \json_decode($lines[0], true);
        $this->assertSame(self::JSON_HISTORY_SIGNATURE, $signature);

        $entry1 = \json_decode($lines[1], true);
        $this->assertSame('echo "hello"', $entry1['command']);
        $this->assertSame(1, $entry1['lines']);

        $entry2 = \json_decode($lines[2], true);
        $this->assertSame("function foo() {\n    return 42;\n}", $entry2['command']);
        $this->assertSame(3, $entry2['lines']);

        @\unlink($tempFile);
    }

    public function testSaveToFileCreatesDirectory()
    {
        $tempDir = \sys_get_temp_dir().'/psysh_test_'.\uniqid();
        $tempFile = $tempDir.'/history';

        $this->assertDirectoryDoesNotExist($tempDir);

        $history = new History();
        $history->add('command');
        $history->saveToFile($tempFile);

        $this->assertFileExists($tempFile);

        @\unlink($tempFile);
        @\rmdir($tempDir);
    }

    public function testSaveEmptyHistory()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');

        $history = new History();
        $history->saveToFile($tempFile);

        // Should create file with signature and no entries.
        $this->assertFileExists($tempFile);
        $lines = \explode("\n", \trim(\file_get_contents($tempFile)));
        $this->assertCount(1, $lines);
        $signature = \json_decode($lines[0], true);
        $this->assertSame(self::JSON_HISTORY_SIGNATURE, $signature);

        @\unlink($tempFile);
    }

    public function testRoundTripJsonl()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'psysh_history_test_');
        @\unlink($tempFile);

        // Create history with various commands
        $history1 = new History();
        $history1->add('echo "hello"');
        $history1->add("function foo() {\n    return 42;\n}");
        $history1->add('$items = range(1, 10)');

        // Save
        $history1->saveToFile($tempFile);

        // Load into new history
        $history2 = new History();
        $history2->loadFromFile($tempFile);

        // Should match
        $this->assertSame($history1->getCount(), $history2->getCount());

        $entries1 = $history1->getAll();
        $entries2 = $history2->getAll();

        $this->assertSame($entries1[0]['command'], $entries2[0]['command']);
        $this->assertSame($entries1[0]['lines'], $entries2[0]['lines']);
        $this->assertSame($entries1[1]['command'], $entries2[1]['command']);
        $this->assertSame($entries1[1]['lines'], $entries2[1]['lines']);
        $this->assertSame($entries1[2]['command'], $entries2[2]['command']);
        $this->assertSame($entries1[2]['lines'], $entries2[2]['lines']);

        @\unlink($tempFile);
    }

    public function testNavigationBouncesBackwardExitsForward()
    {
        $history = new History();
        $history->add('command1');
        $history->add('command2');

        $history->saveTemporaryEntry('partial');

        // Navigate backward, bounces at oldest
        $this->assertSame('command2', $history->getPrevious());
        $this->assertSame('command1', $history->getPrevious());
        $this->assertNull($history->getPrevious());
        $this->assertTrue($history->isInHistory());

        // Navigate forward, exits and restores input
        $this->assertSame('command2', $history->getNext());
        $this->assertSame('partial', $history->getNext());
        $this->assertFalse($history->isInHistory());
    }

    public function testGetDisplayCommand()
    {
        $history = new History();

        // Test single-line command
        $entry = [
            'command'   => 'echo "hello"',
            'timestamp' => \time(),
            'lines'     => 1,
        ];
        $this->assertSame('echo "hello"', $history->getDisplayCommand($entry));

        // Test multi-line command, should collapse to single line
        $entry = [
            'command'   => "function foo() {\n    echo 'test';\n    return 42;\n}",
            'timestamp' => \time(),
            'lines'     => 4,
        ];
        $this->assertSame("function foo() { echo 'test'; return 42; }", $history->getDisplayCommand($entry));

        // Test multi-line with empty lines, should be filtered
        $entry = [
            'command'   => "function foo() {\n\n    return 42;\n\n}",
            'timestamp' => \time(),
            'lines'     => 5,
        ];
        $this->assertSame('function foo() { return 42; }', $history->getDisplayCommand($entry));

        // Test array syntax
        $entry = [
            'command'   => "[\n    1,\n    2,\n    3\n]",
            'timestamp' => \time(),
            'lines'     => 5,
        ];
        $this->assertSame('[ 1, 2, 3 ]', $history->getDisplayCommand($entry));
    }

    public function testGetPreviousReturnsOriginalMultiLineFormat()
    {
        $history = new History();

        // Add multi-line commands
        $history->add("function foo() {\n    return 42;\n}");
        $history->add("[\n    1,\n    2\n]");

        // getPrevious should return original format (not collapsed)
        $this->assertSame("[\n    1,\n    2\n]", $history->getPrevious());
        $this->assertSame("function foo() {\n    return 42;\n}", $history->getPrevious());
    }

    public function testFilteredNavigationSubstringMatch()
    {
        $history = new History();
        $history->add('echo "hello"');
        $history->add('$foo = 42');
        $history->add('echo "goodbye"');
        $history->add('$bar = 99');
        $history->add('echo "world"');

        $history->setSearchTerm('echo');

        // Should skip non-matching entries
        $this->assertSame('echo "world"', $history->getPrevious());
        $this->assertSame('echo "goodbye"', $history->getPrevious());
        $this->assertSame('echo "hello"', $history->getPrevious());
        $this->assertNull($history->getPrevious()); // No more matches
    }

    public function testFilteredNavigationForward()
    {
        $history = new History();
        $history->add('echo "hello"');
        $history->add('$foo = 42');
        $history->add('echo "goodbye"');
        $history->add('$bar = 99');
        $history->add('echo "world"');

        $history->setSearchTerm('echo');
        $history->saveTemporaryEntry('echo');

        // Navigate backward through matches
        $history->getPrevious(); // echo "world"
        $history->getPrevious(); // echo "goodbye"
        $history->getPrevious(); // echo "hello"

        // Navigate forward through matches, then exit
        $this->assertSame('echo "goodbye"', $history->getNext());
        $this->assertSame('echo "world"', $history->getNext());
        $this->assertSame('echo', $history->getNext()); // Restores input
        $this->assertFalse($history->isInHistory());
    }

    public function testFilteredNavigationNoMatches()
    {
        $history = new History();
        $history->add('echo "hello"');
        $history->add('$foo = 42');

        $history->setSearchTerm('nonexistent');

        $this->assertNull($history->getPrevious());
        $this->assertFalse($history->isInHistory());
    }

    public function testFilteredNavigationSmartCaseLowercase()
    {
        $history = new History();
        $history->add('echo Hello');
        $history->add('ECHO WORLD');
        $history->add('Echo Goodbye');

        // Lowercase search term = case-insensitive
        $history->setSearchTerm('echo');

        $this->assertSame('Echo Goodbye', $history->getPrevious());
        $this->assertSame('ECHO WORLD', $history->getPrevious());
        $this->assertSame('echo Hello', $history->getPrevious());
    }

    public function testFilteredNavigationSmartCaseUppercase()
    {
        $history = new History();
        $history->add('echo hello');
        $history->add('ECHO WORLD');
        $history->add('Echo Goodbye');

        // Uppercase in search term = case-sensitive
        $history->setSearchTerm('ECHO');

        $this->assertSame('ECHO WORLD', $history->getPrevious());
        $this->assertNull($history->getPrevious());
    }

    public function testFilteredNavigationSmartCaseMixedCase()
    {
        $history = new History();
        $history->add('echo hello');
        $history->add('Echo Goodbye');
        $history->add('ECHO WORLD');

        // Mixed case search = case-sensitive
        $history->setSearchTerm('Echo');

        $this->assertSame('Echo Goodbye', $history->getPrevious());
        $this->assertNull($history->getPrevious());
    }

    public function testFilteredNavigationMatchesAnywhere()
    {
        $history = new History();
        $history->add('function bar() { echo "bar"; }');
        $history->add('$x = "test"');
        $history->add('echo "hello"');

        // Substring match, not prefix
        $history->setSearchTerm('bar');

        $this->assertSame('function bar() { echo "bar"; }', $history->getPrevious());
        $this->assertNull($history->getPrevious());
    }

    public function testNullSearchTermDisablesFiltering()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');
        $history->add('third');

        $history->setSearchTerm(null);

        // Should behave like unfiltered navigation
        $this->assertSame('third', $history->getPrevious());
        $this->assertSame('second', $history->getPrevious());
        $this->assertSame('first', $history->getPrevious());
    }

    public function testEmptySearchTermDisablesFiltering()
    {
        $history = new History();
        $history->add('first');
        $history->add('second');

        $history->setSearchTerm('');

        $this->assertSame('second', $history->getPrevious());
        $this->assertSame('first', $history->getPrevious());
    }

    public function testResetClearsSearchTerm()
    {
        $history = new History();
        $history->add('echo "hello"');
        $history->add('$foo = 42');

        $history->setSearchTerm('echo');
        $this->assertSame('echo', $history->getSearchTerm());

        $history->reset();
        $this->assertNull($history->getSearchTerm());
    }

    public function testNavigationSkipsConsecutiveDuplicates()
    {
        $history = new History();
        $history->add('echo "hello"');
        $history->add('$foo = 42');
        $history->add('$foo = 42');
        $history->add('$foo = 42');
        $history->add('echo "world"');

        // Going back: should skip the repeated '$foo = 42' entries
        $this->assertSame('echo "world"', $history->getPrevious());
        $this->assertSame('$foo = 42', $history->getPrevious());
        $this->assertSame('echo "hello"', $history->getPrevious());
        $this->assertNull($history->getPrevious());
    }

    public function testNavigationShowsDuplicateAfterDifferentEntry()
    {
        $history = new History();
        $history->add('$foo = 42');
        $history->add('echo "hello"');
        $history->add('$foo = 42');

        // Should see $foo, then echo, then $foo again (not consecutive)
        $this->assertSame('$foo = 42', $history->getPrevious());
        $this->assertSame('echo "hello"', $history->getPrevious());
        $this->assertSame('$foo = 42', $history->getPrevious());
    }

    public function testFilteredNavigationRestoresTemporaryEntry()
    {
        $history = new History();
        $history->add('echo "hello"');
        $history->add('$foo = 42');
        $history->add('echo "world"');

        $history->setSearchTerm('echo');
        $history->saveTemporaryEntry('ech');

        $history->getPrevious(); // echo "world"

        // Forward past last match, restores input
        $this->assertSame('ech', $history->getNext());
        $this->assertFalse($history->isInHistory());
    }

    private function fixturePath(string $path): string
    {
        return __DIR__.'/../../../Fixtures/'.$path;
    }
}
