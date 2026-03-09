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

use Psy\Readline\Interactive\Input\Key;
use Psy\Readline\Interactive\Input\StdinReader;
use Psy\Test\TestCase;

class StdinReaderTest extends TestCase
{
    public function testReadSingleChar()
    {
        $stream = \fopen('php://memory', 'r+');
        \fwrite($stream, 'a');
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        $this->assertTrue($key->isChar());
        $this->assertSame('a', $key->getValue());
        $this->assertSame(Key::TYPE_CHAR, $key->getType());

        \fclose($stream);
    }

    public function testReadControlChar()
    {
        $stream = \fopen('php://memory', 'r+');
        \fwrite($stream, "\x03"); // Ctrl-C
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        $this->assertTrue($key->isControl());
        $this->assertSame("\x03", $key->getValue());
        $this->assertSame(Key::TYPE_CONTROL, $key->getType());

        \fclose($stream);
    }

    public function testReadEof()
    {
        $stream = \fopen('php://memory', 'r+');
        // Don't write anything, stream is at EOF

        $input = new StdinReader($stream);
        $key = $input->readKey();

        $this->assertTrue($key->isEof());
        $this->assertSame('', $key->getValue());
        $this->assertSame(Key::TYPE_EOF, $key->getType());

        \fclose($stream);
    }

    /**
     * Test detecting paste with multiple lines.
     *
     * Note: This is hard to test in unit tests because it relies on
     * stream_select and non-blocking I/O behavior that doesn't work
     * well with memory streams. This test documents the expected
     * behavior but may not actually trigger paste detection.
     */
    public function testPasteDetection()
    {
        $stream = \fopen('php://memory', 'r+');
        $pastedContent = "line1\nline2\nline3";
        \fwrite($stream, $pastedContent);
        \rewind($stream);

        $input = new StdinReader($stream);

        // In real usage, if all this content arrives at once,
        // it would be detected as a paste. In this test environment,
        // it might just read the first character.
        $key = $input->readKey();

        // The test might not detect it as paste in this environment
        // but we can at least verify it doesn't crash
        $this->assertInstanceOf(Key::class, $key);

        \fclose($stream);
    }

    public function testEscapeSequence()
    {
        $stream = \fopen('php://memory', 'r+');
        \fwrite($stream, "\033[A"); // Up arrow
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        $this->assertTrue($key->isEscape());
        $this->assertSame("\033[A", $key->getValue());
        $this->assertSame(Key::TYPE_ESCAPE, $key->getType());

        \fclose($stream);
    }

    public function testCsiUModifiedKeySequence()
    {
        $stream = \fopen('php://memory', 'r+');
        \fwrite($stream, "\033[13;2u"); // Shift+Enter in CSI-u mode
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        $this->assertTrue($key->isEscape());
        $this->assertSame("\033[13;2u", $key->getValue());
        $this->assertSame(Key::TYPE_ESCAPE, $key->getType());

        \fclose($stream);
    }

    public function testCsiUModifiedKeySequenceWithEventType()
    {
        $stream = \fopen('php://memory', 'r+');
        \fwrite($stream, "\033[13;2:1u"); // Shift+Enter with event type suffix
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        $this->assertTrue($key->isEscape());
        $this->assertSame("\033[13;2:1u", $key->getValue());
        $this->assertSame(Key::TYPE_ESCAPE, $key->getType());

        \fclose($stream);
    }

    public function testModifiedTildeSequence()
    {
        $stream = \fopen('php://memory', 'r+');
        \fwrite($stream, "\033[27;2;13~"); // Shift+Enter in modifyOtherKeys mode
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        $this->assertTrue($key->isEscape());
        $this->assertSame("\033[27;2;13~", $key->getValue());
        $this->assertSame(Key::TYPE_ESCAPE, $key->getType());

        \fclose($stream);
    }

    public function testEscPlusEnterSequence()
    {
        $stream = \fopen('php://memory', 'r+');
        \fwrite($stream, "\033\r");
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        $this->assertTrue($key->isEscape());
        $this->assertSame("\033\r", $key->getValue());
        $this->assertSame(Key::TYPE_ESCAPE, $key->getType());

        \fclose($stream);
    }

    public function testNewlineHandling()
    {
        $stream = \fopen('php://memory', 'r+');
        \fwrite($stream, "\n");
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        // Newlines are treated as regular chars, not control chars
        $this->assertTrue($key->isChar());
        $this->assertSame("\n", $key->getValue());

        \fclose($stream);
    }

    /**
     * Test bracketed paste detection.
     *
     * Bracketed paste wraps pasted content with:
     * - \033[200~ before the content
     * - \033[201~ after the content
     */
    public function testBracketedPasteDetection()
    {
        $stream = \fopen('php://memory', 'r+');
        $pastedContent = "<?php\necho 'hello';\n";
        \fwrite($stream, "\033[200~".$pastedContent."\033[201~");
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        // Should detect as paste
        $this->assertTrue($key->isPaste());
        $this->assertSame($pastedContent, $key->getValue());
        $this->assertSame(Key::TYPE_PASTE, $key->getType());

        \fclose($stream);
    }

    /**
     * Test bracketed paste with escape sequences in content.
     */
    public function testBracketedPasteWithEscapeSequences()
    {
        $stream = \fopen('php://memory', 'r+');
        // Content includes escape sequences (e.g., from colored output)
        $pastedContent = "Hello \033[1;31mRed\033[0m World";
        \fwrite($stream, "\033[200~".$pastedContent."\033[201~");
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        // Should preserve escape sequences in the pasted content
        $this->assertTrue($key->isPaste());
        $this->assertSame($pastedContent, $key->getValue());

        \fclose($stream);
    }

    /**
     * Test bracketed paste start marker without end.
     */
    public function testBracketedPasteIncomplete()
    {
        $stream = \fopen('php://memory', 'r+');
        // Start marker but no end marker (EOF)
        \fwrite($stream, "\033[200~some content");
        \rewind($stream);

        $input = new StdinReader($stream);
        $key = $input->readKey();

        // Should still return as paste even without end marker
        $this->assertTrue($key->isPaste());
        $this->assertSame('some content', $key->getValue());

        \fclose($stream);
    }
}
