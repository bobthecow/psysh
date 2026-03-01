<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive;

use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\StdinReader;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;
use Symfony\Component\Console\Output\StreamOutput;

class MultiLinePasteTest extends TestCase
{
    private Readline $readline;
    private Terminal $terminal;

    protected function setUp(): void
    {
        if (!\class_exists('Symfony\Component\Console\Cursor')) {
            $this->markTestSkipped('Interactive readline requires Symfony Console 5.1+');
        }

        $input = $this->createMock(StdinReader::class);
        $output = $this->createMock(StreamOutput::class);

        $this->terminal = new Terminal($input, $output);
        $this->readline = new Readline($this->terminal);
    }

    public function testSingleLinePaste()
    {
        $buffer = new Buffer();
        $this->handlePastedContent("echo 'single line';", $buffer);

        $this->assertSame("echo 'single line';", $buffer->getText());
        $this->assertFalse($this->readline->isMultilineMode());
    }

    public function testMultiLinePaste()
    {
        $buffer = new Buffer();
        $this->handlePastedContent("function test() {\n    echo 'line1';\n    echo 'line2';\n}", $buffer);

        $this->assertSame("function test() {\n    echo 'line1';\n    echo 'line2';\n}", $buffer->getText());
    }

    public function testPasteWithWindowsLineEndings()
    {
        $buffer = new Buffer();
        $this->handlePastedContent("line1\r\nline2\r\nline3", $buffer);

        $this->assertSame("line1\nline2\nline3", $buffer->getText());
    }

    public function testPasteWithTrailingNewline()
    {
        $buffer = new Buffer();
        $this->handlePastedContent("echo 'test';\n", $buffer);

        $this->assertSame("echo 'test';\n", $buffer->getText());
    }

    public function testPasteWithEmptyLines()
    {
        $buffer = new Buffer();
        $this->handlePastedContent("line1\n\nline3\n\nline5", $buffer);

        $this->assertSame("line1\n\nline3\n\nline5", $buffer->getText());
    }

    public function testPasteIntoExistingContent()
    {
        $buffer = new Buffer();
        $buffer->insert('existing ');

        $this->handlePastedContent("pasted\ncontent", $buffer);

        $this->assertSame("existing pasted\ncontent", $buffer->getText());
    }

    /**
     * Invoke the private handlePastedContent method.
     */
    private function handlePastedContent(string $content, Buffer $buffer): void
    {
        $method = new \ReflectionMethod($this->readline, 'handlePastedContent');
        if (\PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $method->invoke($this->readline, $content, $buffer);
    }
}
