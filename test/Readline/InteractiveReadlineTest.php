<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline;

use Psy\Exception\ThrowUpException;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\InteractiveSession;
use Psy\Readline\Interactive\Readline as InternalReadline;
use Psy\Readline\InteractiveReadline;

class InteractiveReadlineTest extends \Psy\Test\TestCase
{
    private const JSON_HISTORY_SIGNATURE = [
        'type'    => 'psysh-history',
        'format'  => 'jsonl',
        'version' => 1,
    ];

    public function testLegacyHistoryFileIsImportOnly()
    {
        $legacyFile = \tempnam(\sys_get_temp_dir(), 'psysh_test_history_legacy_');
        $legacyContents = "legacy one\nlegacy two\n";
        \file_put_contents($legacyFile, $legacyContents);

        $history = new History();
        $readline = $this->newReadline($history, $legacyFile);

        $this->assertTrue($readline->readHistory());
        $this->assertSame(['legacy one', 'legacy two'], $readline->listHistory());

        $readline->addHistory('new command');

        $this->assertSame($legacyContents, \file_get_contents($legacyFile));
        $this->assertFileExists($legacyFile.'.jsonl');

        $savedHistory = new History();
        $savedHistory->loadFromFile($legacyFile.'.jsonl');
        $savedCommands = \array_map(fn ($entry) => $entry['command'], $savedHistory->getAll());
        $this->assertSame(['legacy one', 'legacy two', 'new command'], $savedCommands);

        @\unlink($legacyFile.'.jsonl');
        @\unlink($legacyFile);
    }

    public function testExistingJsonlHistoryTakesPrecedenceOverLegacy()
    {
        $legacyFile = \tempnam(\sys_get_temp_dir(), 'psysh_test_history_legacy_');
        \file_put_contents($legacyFile, "legacy one\n");

        $jsonlFile = $legacyFile.'.jsonl';
        \file_put_contents(
            $jsonlFile,
            \json_encode(self::JSON_HISTORY_SIGNATURE)."\n"
            .\json_encode(['command' => 'jsonl one', 'timestamp' => 123, 'lines' => 1])."\n"
        );

        $history = new History();
        $readline = $this->newReadline($history, $legacyFile);

        $this->assertTrue($readline->readHistory());
        $this->assertSame(['jsonl one'], $readline->listHistory());

        $readline->addHistory('new command');

        $this->assertSame("legacy one\n", \file_get_contents($legacyFile));

        $savedHistory = new History();
        $savedHistory->loadFromFile($jsonlFile);
        $savedCommands = \array_map(fn ($entry) => $entry['command'], $savedHistory->getAll());
        $this->assertSame(['jsonl one', 'new command'], $savedCommands);

        @\unlink($jsonlFile);
        @\unlink($legacyFile);
    }

    public function testJsonlHistoryFileWithSignatureIsNotTreatedAsLegacy()
    {
        $historyFile = \tempnam(\sys_get_temp_dir(), 'psysh_test_history_jsonl_');
        \file_put_contents($historyFile, \json_encode(self::JSON_HISTORY_SIGNATURE)."\n");

        $history = new History();
        $history->add('new command');
        $readline = $this->newReadline($history, $historyFile);
        $readline->writeHistory();

        $this->assertFileDoesNotExist($historyFile.'.jsonl');

        $savedHistory = new History();
        $savedHistory->loadFromFile($historyFile);
        $savedCommands = \array_map(fn ($entry) => $entry['command'], $savedHistory->getAll());
        $this->assertSame(['new command'], $savedCommands);

        @\unlink($historyFile);
    }

    public function testClearHistory()
    {
        $history = new History();
        $history->add('foo');
        $history->add('bar');

        $historyFile = \tempnam(\sys_get_temp_dir(), 'psysh_test_history');
        $readline = $this->newReadline($history, $historyFile);

        $this->assertTrue($readline->clearHistory());
        $this->assertSame([], $readline->listHistory());
        $lines = \explode("\n", \trim(\file_get_contents($historyFile)));
        $this->assertCount(1, $lines);
        $signature = \json_decode($lines[0], true);
        $this->assertSame(self::JSON_HISTORY_SIGNATURE, $signature);

        @\unlink($historyFile);
    }

    public function testListHistoryIsChronological()
    {
        $history = new History();
        $history->add('foo');
        $history->add('bar');

        $readline = $this->newReadline($history, false);

        $this->assertSame(['foo', 'bar'], $readline->listHistory());
    }

    public function testSetOutputDoesNotStartInteractiveSession()
    {
        if (!\class_exists('Symfony\Component\Console\Cursor')) {
            $this->markTestSkipped('Interactive readline requires Symfony Console 5.1+');
        }

        $readline = new InteractiveReadline(false);
        $readline->setOutput(new \Symfony\Component\Console\Output\StreamOutput(\STDOUT));
        $session = $this->getPrivateProperty($readline, 'session');

        $this->assertInstanceOf(InteractiveSession::class, $session);
        $this->assertFalse($session->isActive());
    }

    public function testReadlineStartsSessionBeforeReading()
    {
        $reflection = new \ReflectionClass(InteractiveReadline::class);
        $interactiveReadline = $reflection->newInstanceWithoutConstructor();

        $session = $this->getMockBuilder(InteractiveSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['start'])
            ->getMock();
        $session->expects($this->once())
            ->method('start');

        $internalReadline = $this->getMockBuilder(InternalReadline::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['readline'])
            ->getMock();
        $internalReadline->expects($this->once())
            ->method('readline')
            ->willReturn('echo 42;');

        $this->setPrivateProperty($interactiveReadline, 'booted', true);
        $this->setPrivateProperty($interactiveReadline, 'session', $session);
        $this->setPrivateProperty($interactiveReadline, 'readline', $internalReadline);

        $this->assertSame('echo 42;', $interactiveReadline->readline('>>> '));
    }

    public function testReadlineRethrowsSessionStartFailureAsThrowUpException()
    {
        $reflection = new \ReflectionClass(InteractiveReadline::class);
        $interactiveReadline = $reflection->newInstanceWithoutConstructor();

        $session = $this->getMockBuilder(InteractiveSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['start'])
            ->getMock();
        $session->expects($this->once())
            ->method('start')
            ->willThrowException(new \RuntimeException('Unable to enable raw mode for interactive readline.'));

        $internalReadline = $this->getMockBuilder(InternalReadline::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['readline'])
            ->getMock();
        $internalReadline->expects($this->never())->method('readline');

        $this->setPrivateProperty($interactiveReadline, 'booted', true);
        $this->setPrivateProperty($interactiveReadline, 'session', $session);
        $this->setPrivateProperty($interactiveReadline, 'readline', $internalReadline);

        try {
            $interactiveReadline->readline('>>> ');
            $this->fail('Expected ThrowUpException to be thrown');
        } catch (ThrowUpException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertSame('Unable to enable raw mode for interactive readline.', $e->getPrevious()->getMessage());
        }
    }

    public function testSetBracketedPasteDelegatesToSession()
    {
        $reflection = new \ReflectionClass(InteractiveReadline::class);
        $interactiveReadline = $reflection->newInstanceWithoutConstructor();

        $session = $this->getMockBuilder(InteractiveSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setBracketedPaste'])
            ->getMock();
        $session->expects($this->once())
            ->method('setBracketedPaste')
            ->with(true);

        $this->setPrivateProperty($interactiveReadline, 'booted', true);
        $this->setPrivateProperty($interactiveReadline, 'session', $session);
        $interactiveReadline->setBracketedPaste(true);
    }

    private function newReadline(History $history, $historyFile): InteractiveReadline
    {
        $reflection = new \ReflectionClass(InteractiveReadline::class);
        $readline = $reflection->newInstanceWithoutConstructor();

        $this->setPrivateProperty($readline, 'booted', true);
        $this->setPrivateProperty($readline, 'history', $history);
        $this->setPrivateProperty($readline, 'historyFile', $historyFile);

        return $readline;
    }

    private function setPrivateProperty(object $target, string $property, $value): void
    {
        $prop = new \ReflectionProperty($target, $property);
        if (\PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $prop->setValue($target, $value);
    }

    private function getPrivateProperty(object $target, string $property)
    {
        $prop = new \ReflectionProperty($target, $property);
        if (\PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }

        return $prop->getValue($target);
    }
}
