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

use Psy\Readline\Interactive\Input\Buffer;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class BufferTest extends TestCase
{
    use BufferAssertionTrait;

    public function testAutomaticSemicolonInsertionEnabled()
    {
        $buffer = new Buffer(false); // requireSemicolons = false (default)

        $buffer->setText('echo "hello"');
        $this->assertTrue($buffer->isCompleteStatement(), 'echo without semicolon should be complete');

        $buffer->setText('$x = 42');
        $this->assertTrue($buffer->isCompleteStatement(), 'assignment without semicolon should be complete');

        $buffer->setText('2 + 2');
        $this->assertTrue($buffer->isCompleteStatement(), 'expression without semicolon should be complete');

        $buffer->setText('strlen("test")');
        $this->assertTrue($buffer->isCompleteStatement(), 'function call without semicolon should be complete');
    }

    public function testHasUnclosedBracketsBeforeCursor()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'foo(<cursor>)');

        $this->assertTrue($buffer->hasUnclosedBracketsBeforeCursor());

        $this->setBufferState($buffer, 'foo()<cursor>');
        $this->assertFalse($buffer->hasUnclosedBracketsBeforeCursor());
    }

    public function testCalculateIndentBeforeCursor()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'if ($x) {<cursor>}');

        $this->assertSame('    ', $buffer->calculateIndentBeforeCursor());
    }

    public function testAutomaticSemicolonInsertionDisabled()
    {
        $buffer = new Buffer(true); // requireSemicolons = true

        $buffer->setText('echo "hello"');
        $this->assertFalse($buffer->isCompleteStatement(), 'echo without semicolon should be incomplete when requireSemicolons=true');

        $buffer->setText('echo "hello";');
        $this->assertTrue($buffer->isCompleteStatement(), 'echo with semicolon should be complete');
    }

    public function testIncompleteStatementsStillIncomplete()
    {
        $buffer = new Buffer(false); // Automatic semicolon insertion enabled

        $buffer->setText('echo "hello');
        $this->assertFalse($buffer->isCompleteStatement(), 'unclosed string should be incomplete');

        $buffer->setText('function foo() {');
        $this->assertFalse($buffer->isCompleteStatement(), 'unclosed brace should be incomplete');

        $buffer->setText('2 +');
        $this->assertFalse($buffer->isCompleteStatement(), 'trailing operator should be incomplete');

        $buffer->setText('function foo() {;');
        $this->assertFalse($buffer->isCompleteStatement(), 'syntax error should be incomplete even with semicolon');
    }

    public function testRealSyntaxErrorsNotFixedBySemicolon()
    {
        $buffer = new Buffer(false);

        $buffer->setText('if (');
        $this->assertFalse($buffer->isCompleteStatement(), 'broken if statement should be incomplete');

        $buffer->setText('class {');
        $this->assertTrue($buffer->isCompleteStatement(), 'class without name is syntax error, should execute to show error');

        $buffer->setText('function {');
        $this->assertTrue($buffer->isCompleteStatement(), 'function without name is syntax error, should execute to show error');
    }

    public function testNewBufferIsEmpty(): void
    {
        $buffer = new Buffer();

        $this->assertBufferState('<cursor>', $buffer);
        $this->assertSame(0, $buffer->getLength());
    }

    public function testSetText(): void
    {
        $buffer = new Buffer();
        $buffer->setText('hello');

        $this->assertBufferState('hello<cursor>', $buffer);
        $this->assertSame(5, $buffer->getLength());
    }

    public function testClear(): void
    {
        $buffer = new Buffer();
        $buffer->setText('hello');
        $buffer->clear();

        $this->assertBufferState('<cursor>', $buffer);
    }

    public function testInsertAtEnd(): void
    {
        $buffer = new Buffer();
        $buffer->setText('hello');
        $buffer->insert(' world');

        $this->assertBufferState('hello world<cursor>', $buffer);
    }

    public function testInsertAtCursor(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'he<cursor>llo');
        $buffer->insert('XX');

        $this->assertBufferState('heXX<cursor>llo', $buffer);
    }

    public function testInsertAtStart(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>world');
        $buffer->insert('hello ');

        $this->assertBufferState('hello <cursor>world', $buffer);
    }

    public function testDeleteBackward(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello<cursor>');

        $result = $buffer->deleteBackward(2);

        $this->assertTrue($result);
        $this->assertBufferState('hel<cursor>', $buffer);
    }

    public function testDeleteBackwardAtStart(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>hello');

        $result = $buffer->deleteBackward();

        $this->assertFalse($result);
        $this->assertBufferState('<cursor>hello', $buffer);
    }

    public function testDeleteBackwardMultiple(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hel<cursor>lo');

        $result = $buffer->deleteBackward(2);

        $this->assertTrue($result);
        $this->assertBufferState('h<cursor>lo', $buffer);
    }

    public function testDeleteForward(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'he<cursor>llo');

        $result = $buffer->deleteForward(2);

        $this->assertTrue($result);
        $this->assertBufferState('he<cursor>o', $buffer);
    }

    public function testDeleteForwardAtEnd(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello<cursor>');

        $result = $buffer->deleteForward();

        $this->assertFalse($result);
        $this->assertBufferState('hello<cursor>', $buffer);
    }

    public function testDeleteToEnd(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello<cursor> world');

        $killed = $buffer->deleteToEnd();

        $this->assertSame(' world', $killed);
        $this->assertBufferState('hello<cursor>', $buffer);
    }

    public function testDeleteToEndMultiline(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "line1\nli<cursor>ne2\nline3");

        $killed = $buffer->deleteToEnd();

        $this->assertSame('ne2', $killed);
        $this->assertBufferState("line1\nli<cursor>\nline3", $buffer);
    }

    public function testDeleteToEndOnLastLine(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "line1\nli<cursor>ne2");

        $killed = $buffer->deleteToEnd();

        $this->assertSame('ne2', $killed);
        $this->assertBufferState("line1\nli<cursor>", $buffer);
    }

    public function testDeleteToEndAtNewline(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "line1\nline2<cursor>\nline3");

        $killed = $buffer->deleteToEnd();

        $this->assertSame('', $killed);
        $this->assertBufferState("line1\nline2<cursor>\nline3", $buffer);
    }

    public function testDeleteToStart(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello <cursor>world');

        $killed = $buffer->deleteToStart();

        $this->assertSame('hello ', $killed);
        $this->assertBufferState('<cursor>world', $buffer);
    }

    public function testDeleteToStartMultiline(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "line1\nli<cursor>ne2\nline3");

        $killed = $buffer->deleteToStart();

        $this->assertSame('li', $killed);
        $this->assertBufferState("line1\n<cursor>ne2\nline3", $buffer);
    }

    public function testDeleteToStartOnFirstLine(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "lin<cursor>e1\nline2");

        $killed = $buffer->deleteToStart();

        $this->assertSame('lin', $killed);
        $this->assertBufferState("<cursor>e1\nline2", $buffer);
    }

    public function testDeleteToStartAtStartOfLine(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "line1\n<cursor>line2\nline3");

        $killed = $buffer->deleteToStart();

        $this->assertSame('', $killed);
        $this->assertBufferState("line1\n<cursor>line2\nline3", $buffer);
    }

    public function testMoveCursorLeft(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hel<cursor>lo');

        $moved = $buffer->moveCursorLeft(2);

        $this->assertSame(2, $moved);
        $this->assertBufferState('h<cursor>ello', $buffer);
    }

    public function testMoveCursorLeftBeyondStart(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'he<cursor>llo');

        $moved = $buffer->moveCursorLeft(5);

        $this->assertSame(2, $moved);
        $this->assertBufferState('<cursor>hello', $buffer);
    }

    public function testMoveCursorRight(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'he<cursor>llo');

        $moved = $buffer->moveCursorRight(2);

        $this->assertSame(2, $moved);
        $this->assertBufferState('hell<cursor>o', $buffer);
    }

    public function testMoveCursorRightBeyondEnd(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hel<cursor>lo');

        $moved = $buffer->moveCursorRight(5);

        $this->assertSame(2, $moved);
        $this->assertBufferState('hello<cursor>', $buffer);
    }

    public function testMoveCursorToStart(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hel<cursor>lo');

        $buffer->moveCursorToStart();

        $this->assertBufferState('<cursor>hello', $buffer);
    }

    public function testMoveCursorToEnd(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'he<cursor>llo');

        $buffer->moveCursorToEnd();

        $this->assertBufferState('hello<cursor>', $buffer);
    }

    public function testGetBeforeCursor(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello<cursor> world');

        $this->assertSame('hello', $buffer->getBeforeCursor());
    }

    public function testGetAfterCursor(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello <cursor>world');

        $this->assertSame('world', $buffer->getAfterCursor());
    }

    public function testMultibyteCharacters(): void
    {
        $buffer = new Buffer();
        $buffer->setText('hello世界');

        $this->assertSame(7, $buffer->getLength());

        $this->setBufferState($buffer, 'hello<cursor>世界');
        $buffer->insert('👋');

        $this->assertBufferState('hello👋<cursor>世界', $buffer);
    }

    public function testMultibyteDelete(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello👋<cursor>world');

        $buffer->deleteBackward(1);

        $this->assertBufferState('hello<cursor>world', $buffer);
    }

    public function testFindPreviousWord(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello world foo<cursor>');

        $pos = $buffer->findPreviousWord();

        $this->assertSame(12, $pos);
    }

    public function testFindPreviousWordWithSpaces(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello world   <cursor>');

        $pos = $buffer->findPreviousWord();

        $this->assertSame(6, $pos);
    }

    public function testFindNextWord(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>hello world foo');

        $pos = $buffer->findNextWord();

        $this->assertSame(5, $pos);
    }

    public function testFindNextWordWithSpaces(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello<cursor>   world foo');

        $pos = $buffer->findNextWord();

        $this->assertSame(13, $pos);
    }

    public function testDeletePreviousWord(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello world foo<cursor>');

        $killed = $buffer->deletePreviousWord();

        $this->assertSame('foo', $killed);
        $this->assertBufferState('hello world <cursor>', $buffer);
    }

    public function testDeleteNextWord(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>hello world foo');

        $killed = $buffer->deleteNextWord();

        $this->assertSame('hello', $killed);
        $this->assertBufferState('<cursor> world foo', $buffer);
    }

    public function testSetCursorBeyondEnd(): void
    {
        $buffer = new Buffer();
        $buffer->setText('hello');

        $buffer->setCursor(100);

        $this->assertSame(5, $buffer->getCursor());
    }

    public function testSetCursorNegative(): void
    {
        $buffer = new Buffer();
        $buffer->setText('hello');

        $buffer->setCursor(-10);

        $this->assertSame(0, $buffer->getCursor());
    }

    public function testValidCompleteStatement(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$x = 1;');

        $this->assertTrue($buffer->isCompleteStatement());
    }

    public function testValidIncompleteExpression(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$x = 1 +');

        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testUnclosedBracket(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$arr = [1, 2');

        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testUnclosedParenthesis(): void
    {
        $buffer = new Buffer();
        $buffer->setText('foo(1, 2');

        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testUnclosedBrace(): void
    {
        $buffer = new Buffer();
        $buffer->setText('function test() {');

        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testBalancedBrackets(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$arr = [1, 2, 3];');

        $this->assertTrue($buffer->isCompleteStatement());
    }

    public function testCompleteFunction(): void
    {
        $buffer = new Buffer();
        $buffer->setText('function test() { return 1; }');

        $this->assertTrue($buffer->isCompleteStatement());
    }

    public function testIncompleteFunction(): void
    {
        $buffer = new Buffer();
        $buffer->setText('function test() {');

        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testTrailingObjectOperator(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$foo->');

        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testTrailingDoubleColon(): void
    {
        $buffer = new Buffer();
        $buffer->setText('Foo::');

        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testTrailingSingleCharOperator(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$x = 1 +');

        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testEmptyBufferIsComplete(): void
    {
        $buffer = new Buffer();

        $this->assertTrue($buffer->isCompleteStatement());
    }

    public function testReparseAfterModification(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$x = 1;');

        $this->assertTrue($buffer->isCompleteStatement());

        $buffer->insert(' +');

        // After adding '+', it becomes incomplete (trailing operator)
        $this->assertFalse($buffer->isCompleteStatement());
    }

    public function testNestedBrackets(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$x = [[1, 2], [3, 4]];');

        $this->assertTrue($buffer->isCompleteStatement());
    }

    public function testMismatchedBrackets(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$x = [1, 2}');

        // Mismatched brackets are a syntax error that can't be fixed
        // by adding more input, should execute to show error
        $this->assertTrue($buffer->isCompleteStatement());
    }

    public function testFindPreviousTokenSimple(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo<cursor>');

        $pos = $buffer->findPreviousToken();

        $this->assertSame(0, $pos);
    }

    public function testFindPreviousTokenWithOperator(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo->bar<cursor>');

        // First move back, should find start of "bar"
        $pos = $buffer->findPreviousToken();
        $this->assertSame(6, $pos);

        // Set cursor and move back again, should find start of "->"
        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken();
        $this->assertSame(4, $pos);

        // Set cursor and move back again, should find start of "$foo"
        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken();
        $this->assertSame(0, $pos);
    }

    public function testFindPreviousTokenWithStaticCall(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'MyClass::method()<cursor>');

        // Navigate backwards through tokens
        $pos = $buffer->findPreviousToken(); // Should find ")"
        $this->assertSame(16, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken(); // Should find "("
        $this->assertSame(15, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken(); // Should find "method"
        $this->assertSame(9, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken(); // Should find "::"
        $this->assertSame(7, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken(); // Should find "MyClass"
        $this->assertSame(0, $pos);
    }

    public function testFindPreviousTokenSkipsWhitespace(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo   ->   bar<cursor>');

        // Should skip whitespace and find "bar"
        $pos = $buffer->findPreviousToken();
        $this->assertSame(12, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken();
        $this->assertSame(7, $pos); // "->"
    }

    public function testFindPreviousTokenAtStart(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>$foo->bar');

        $pos = $buffer->findPreviousToken();

        $this->assertSame(0, $pos); // Can't go further back
    }

    public function testFindNextTokenSimple(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>$foo');

        $pos = $buffer->findNextToken();

        $this->assertSame(4, $pos); // End of line (no more tokens)
    }

    public function testFindNextTokenWithOperator(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>$foo->bar');

        // First move forward, should find start of "->"
        $pos = $buffer->findNextToken();
        $this->assertSame(4, $pos);

        // Set cursor and move forward again, should find start of "bar"
        $buffer->setCursor($pos);
        $pos = $buffer->findNextToken();
        $this->assertSame(6, $pos);

        // Set cursor and move forward again, should reach end
        $buffer->setCursor($pos);
        $pos = $buffer->findNextToken();
        $this->assertSame(9, $pos); // End of line
    }

    public function testFindNextTokenWithStaticCall(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>MyClass::method()');

        // Navigate forwards through tokens
        $pos = $buffer->findNextToken(); // Should find "::"
        $this->assertSame(7, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findNextToken(); // Should find "method"
        $this->assertSame(9, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findNextToken(); // Should find "("
        $this->assertSame(15, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findNextToken(); // Should find ")"
        $this->assertSame(16, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findNextToken(); // Should reach end
        $this->assertSame(17, $pos);
    }

    public function testFindNextTokenSkipsWhitespace(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>$foo   ->   bar');

        // Should skip whitespace and find "->"
        $pos = $buffer->findNextToken();
        $this->assertSame(7, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findNextToken();
        $this->assertSame(12, $pos); // "bar"
    }

    public function testFindNextTokenAtEnd(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo->bar<cursor>');

        $pos = $buffer->findNextToken();

        $this->assertSame(9, $pos); // Can't go further
    }

    public function testDeletePreviousToken(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo->bar<cursor>');

        $killed = $buffer->deletePreviousToken();

        $this->assertSame('bar', $killed);
        $this->assertBufferState('$foo-><cursor>', $buffer);
    }

    public function testDeletePreviousTokenWithOperator(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo-><cursor>bar');

        $killed = $buffer->deletePreviousToken();

        $this->assertSame('->', $killed);
        $this->assertBufferState('$foo<cursor>bar', $buffer);
    }

    public function testDeletePreviousTokenMultipleTimes(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo->bar()<cursor>');

        // Delete ")"
        $killed = $buffer->deletePreviousToken();
        $this->assertSame(')', $killed);
        $this->assertBufferState('$foo->bar(<cursor>', $buffer);

        // Delete "("
        $killed = $buffer->deletePreviousToken();
        $this->assertSame('(', $killed);
        $this->assertBufferState('$foo->bar<cursor>', $buffer);

        // Delete "bar"
        $killed = $buffer->deletePreviousToken();
        $this->assertSame('bar', $killed);
        $this->assertBufferState('$foo-><cursor>', $buffer);
    }

    public function testDeleteNextToken(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>$foo->bar');

        $killed = $buffer->deleteNextToken();

        $this->assertSame('$foo', $killed);
        $this->assertBufferState('<cursor>->bar', $buffer);
    }

    public function testDeleteNextTokenWithOperator(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo<cursor>->bar');

        $killed = $buffer->deleteNextToken();

        $this->assertSame('->', $killed);
        $this->assertBufferState('$foo<cursor>bar', $buffer);
    }

    public function testDeleteNextTokenMultipleTimes(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>$foo->bar()');

        // Delete "$foo"
        $killed = $buffer->deleteNextToken();
        $this->assertSame('$foo', $killed);
        $this->assertBufferState('<cursor>->bar()', $buffer);

        // Delete "->"
        $killed = $buffer->deleteNextToken();
        $this->assertSame('->', $killed);
        $this->assertBufferState('<cursor>bar()', $buffer);

        // Delete "bar"
        $killed = $buffer->deleteNextToken();
        $this->assertSame('bar', $killed);
        $this->assertBufferState('<cursor>()', $buffer);
    }

    public function testTokenNavigationWithArrayAccess(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$array[0]<cursor>');

        // Navigate backwards through tokens
        $pos = $buffer->findPreviousToken(); // "]"
        $this->assertSame(8, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken(); // "0"
        $this->assertSame(7, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken(); // "["
        $this->assertSame(6, $pos);

        $buffer->setCursor($pos);
        $pos = $buffer->findPreviousToken(); // "$array"
        $this->assertSame(0, $pos);
    }

    public function testTokenNavigationWithComplexExpression(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$this->property->method()');

        // Test forward navigation
        $buffer->setCursor(0);
        $positions = [];
        while (true) {
            $next = $buffer->findNextToken();
            if ($next === $buffer->getCursor() || $next === $buffer->getLength()) {
                break;
            }
            $buffer->setCursor($next);
            $positions[] = $next;
        }

        // Should have found multiple token boundaries
        $this->assertGreaterThan(3, \count($positions));
    }

    public function testTokenNavigationInEmptyBuffer(): void
    {
        $buffer = new Buffer();
        $buffer->setText('');

        $this->assertSame(0, $buffer->findPreviousToken());
        $this->assertSame(0, $buffer->findNextToken());
    }

    public function testDeleteTokensPreservesRemainingText(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo->bar<cursor>->baz');

        // Delete "bar"
        $buffer->deletePreviousToken();
        $this->assertBufferState('$foo-><cursor>->baz', $buffer);
    }

    public function testCalculateIndentAfterOpeningBrace(): void
    {
        $buffer = new Buffer();
        $buffer->setText('function foo() {');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent);
    }

    public function testCalculateIndentAfterOpeningBracket(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$arr = [');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent);
    }

    public function testCalculateIndentAfterOpeningParen(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$result = foo(');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent);
    }

    public function testCalculateIndentWithExistingIndentation(): void
    {
        $buffer = new Buffer();
        $buffer->setText('    if ($x) {');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('        ', $indent); // 4 + 4 spaces
    }

    public function testCalculateIndentAfterControlStructure(): void
    {
        $buffer = new Buffer();
        $buffer->setText('if ($condition)');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent);
    }

    public function testCalculateIndentAfterForeach(): void
    {
        $buffer = new Buffer();
        $buffer->setText('foreach ($items as $item)');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent);
    }

    public function testCalculateIndentAfterComma(): void
    {
        $buffer = new Buffer();
        $buffer->setText('    $x = 1,');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent); // Same level
    }

    public function testCalculateIndentAfterOperator(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$result = $a +');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent); // Same level (no existing indent)
    }

    public function testCalculateIndentForEmptyLine(): void
    {
        $buffer = new Buffer();
        $buffer->setText('');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent);
    }

    public function testCalculateIndentForCompleteStatement(): void
    {
        $buffer = new Buffer();
        $buffer->setText('$x = 1;');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent); // No continuation needed
    }

    public function testCalculateIndentPreservesTabsInExistingIndent(): void
    {
        $buffer = new Buffer();
        $buffer->setText("\tif (\$x) {");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame("\t    ", $indent); // Preserves tab + adds spaces
    }

    public function testCalculateIndentForNestedBlocks(): void
    {
        $buffer = new Buffer();
        $buffer->setText('        while ($x) {'); // Already 8 spaces indented

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('            ', $indent); // 12 spaces (8 + 4)
    }

    public function testMaintainIndentWithinBlock(): void
    {
        $buffer = new Buffer();
        $buffer->setText("function foo() {\n    echo \"wat\";");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent); // Maintain 4 spaces
    }

    public function testMaintainIndentAfterCompleteStatement(): void
    {
        $buffer = new Buffer();
        $buffer->setText("if (\$x) {\n    \$y = 1;\n    \$z = 2;");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent); // Still inside block, maintain indent
    }

    public function testMaintainDeeperIndentWithinNestedBlock(): void
    {
        $buffer = new Buffer();
        $buffer->setText("function foo() {\n    if (\$x) {\n        echo 'test';");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('        ', $indent); // Maintain 8 spaces (nested)
    }

    public function testMaintainIndentAcrossMultipleLines(): void
    {
        $buffer = new Buffer();
        $buffer->setText("class Foo {\n    private \$x;\n    private \$y;");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent); // Stay at class body level
    }

    public function testNoIndentInsideDoubleQuotedString(): void
    {
        $buffer = new Buffer();
        $buffer->setText('echo "hello');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent); // No indent, we're in a string!
    }

    public function testNoIndentInsideSingleQuotedString(): void
    {
        $buffer = new Buffer();
        $buffer->setText("echo 'hello");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent); // No indent, we're in a string!
    }

    public function testNoIndentInsideBacktickString(): void
    {
        $buffer = new Buffer();
        $buffer->setText('echo `ls');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent); // No indent, we're in a backtick command!
    }

    public function testNoIndentWithUnterminatedStringInFunction(): void
    {
        $buffer = new Buffer();
        $buffer->setText("function foo() {\n    echo \"wat");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent); // No indent, inside string
    }

    public function testIndentResumesAfterClosedString(): void
    {
        $buffer = new Buffer();
        $buffer->setText("function foo() {\n    echo \"wat\";");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('    ', $indent); // String is closed, maintain indent
    }

    public function testNoIndentInsideMultilineComment(): void
    {
        $buffer = new Buffer();
        $buffer->setText('/* this is a comment');

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent); // No indent, inside comment
    }

    public function testNoIndentWithStringInNestedBlock(): void
    {
        $buffer = new Buffer();
        $buffer->setText("if (\$x) {\n    if (\$y) {\n        echo \"test");

        $indent = $buffer->calculateNextLineIndent();
        $this->assertSame('', $indent); // No indent, inside string despite nesting
    }

    public function testAutoDedentClosingBrace(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        // User types closing brace at start of line
        $buffer->autoDedentIfClosingBracket('}');
        $buffer->insert('}');

        $this->assertSame('}', $buffer->getText()); // Dedented to no indent
    }

    public function testAutoDedentClosingBracket(): void
    {
        $buffer = new Buffer();
        $buffer->insert('        '); // 8 spaces

        // User types closing bracket
        $buffer->autoDedentIfClosingBracket(']');
        $buffer->insert(']');

        $this->assertSame('    ]', $buffer->getText()); // Removed 4 spaces
    }

    public function testAutoDedentClosingParen(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        $buffer->autoDedentIfClosingBracket(')');
        $buffer->insert(')');

        $this->assertSame(')', $buffer->getText());
    }

    public function testNoDedentIfNotAtStartOfLine(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');
        $buffer->insert('echo');

        // User types closing brace after code
        $buffer->autoDedentIfClosingBracket('}');
        $buffer->insert('}');

        $this->assertSame('    echo}', $buffer->getText()); // No dedent
    }

    public function testNoDedentIfNotAutoInserted(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    '); // User typed manually

        $buffer->autoDedentIfClosingBracket('}');
        $buffer->insert('}');

        // In multi-line mode, we dedent based on whitespace presence, not tracking
        // This is necessary because autoInsertedSpaces doesn't work across multiple lines
        $this->assertSame('}', $buffer->getText()); // Dedents, has leading spaces
    }

    public function testNoDedentForRegularCharacters(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        $buffer->autoDedentIfClosingBracket('a'); // Regular character
        $buffer->insert('a');

        $this->assertSame('    a', $buffer->getText()); // No dedent
    }

    public function testAutoDedentPartialIndent(): void
    {
        $buffer = new Buffer();
        $buffer->insert('  '); // Only 2 spaces

        $buffer->autoDedentIfClosingBracket('}');
        $buffer->insert('}');

        $this->assertSame('}', $buffer->getText()); // Removed 2 spaces (all we had)
    }

    public function testAutoDedentPreservesRemaining(): void
    {
        $buffer = new Buffer();
        $buffer->insert('        '); // 8 spaces

        $buffer->autoDedentIfClosingBracket('}');
        $buffer->insert('}');

        $this->assertSame('    }', $buffer->getText()); // Removed 4, kept 4
    }

    public function testAutoDedentOnlyIfBracketsMatch(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        // Context has opening '[' but we're typing '}'
        $context = '$arr = [';
        $buffer->autoDedentIfClosingBracket('}', $context);
        $buffer->insert('}');

        $this->assertSame('    }', $buffer->getText()); // No dedent, brackets don't match
    }

    public function testAutoDedentWithMatchingBrackets(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        // Context has opening '[', we're typing ']'
        $context = '$arr = [';
        $buffer->autoDedentIfClosingBracket(']', $context);
        $buffer->insert(']');

        $this->assertSame(']', $buffer->getText()); // Dedented, brackets match!
    }

    public function testAutoDedentWithMatchingBraces(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        $context = 'if ($x) {';
        $buffer->autoDedentIfClosingBracket('}', $context);
        $buffer->insert('}');

        $this->assertSame('}', $buffer->getText()); // Dedented, braces match!
    }

    public function testAutoDedentWithMatchingParens(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        $context = 'foo(';
        $buffer->autoDedentIfClosingBracket(')', $context);
        $buffer->insert(')');

        $this->assertSame(')', $buffer->getText()); // Dedented, parens match!
    }

    public function testNoDedentWhenBracketTypeMismatch(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        // Opening '(' but closing ']'
        $context = 'foo(';
        $buffer->autoDedentIfClosingBracket(']', $context);
        $buffer->insert(']');

        $this->assertSame('    ]', $buffer->getText()); // No dedent, mismatch
    }

    public function testAutoDedentWithNestedMatchingBrackets(): void
    {
        $buffer = new Buffer();
        $buffer->insert('        '); // 8 spaces (nested)

        // Context has nested brackets
        $context = "if (\$x) {\n    \$arr = [";
        $buffer->autoDedentIfClosingBracket(']', $context);
        $buffer->insert(']');

        $this->assertSame('    ]', $buffer->getText()); // Dedented, innermost '[' matches ']'
    }

    public function testNoDedentWhenClosingOuterBracket(): void
    {
        $buffer = new Buffer();
        $buffer->insert('        ');

        // Context has nested brackets, but we're closing the outer one
        $context = "if (\$x) {\n    \$arr = [";
        $buffer->autoDedentIfClosingBracket('}', $context);
        $buffer->insert('}');

        $this->assertSame('        }', $buffer->getText()); // No dedent, '[' still open
    }

    public function testAutoDedentIgnoresBracketsInStrings(): void
    {
        $buffer = new Buffer();
        $buffer->insert('    ');

        // Opening '[' is inside a string, real opening is '('
        $context = 'foo("test[", ';
        $buffer->autoDedentIfClosingBracket(')', $context);
        $buffer->insert(')');

        $this->assertSame(')', $buffer->getText()); // Dedented, matches '(' (ignores '[' in string)
    }

    public function testGetCurrentLineNumber(): void
    {
        $buffer = new Buffer();
        $buffer->setText("line1\nline2\nline3");

        $this->setBufferState($buffer, "<cursor>line1\nline2\nline3");
        $this->assertSame(0, $buffer->getCurrentLineNumber());

        $this->setBufferState($buffer, "line1\n<cursor>line2\nline3");
        $this->assertSame(1, $buffer->getCurrentLineNumber());

        $this->setBufferState($buffer, "line1\nline2\n<cursor>line3");
        $this->assertSame(2, $buffer->getCurrentLineNumber());
    }

    public function testGetLineCount(): void
    {
        $buffer = new Buffer();
        $buffer->setText('single line');
        $this->assertSame(1, $buffer->getLineCount());

        $buffer->setText("line1\nline2");
        $this->assertSame(2, $buffer->getLineCount());

        $buffer->setText("line1\nline2\nline3\nline4");
        $this->assertSame(4, $buffer->getLineCount());
    }

    public function testIsOnFirstLine(): void
    {
        $buffer = new Buffer();
        $buffer->setText("line1\nline2\nline3");

        $this->setBufferState($buffer, "<cursor>line1\nline2\nline3");
        $this->assertTrue($buffer->isOnFirstLine());

        $this->setBufferState($buffer, "lin<cursor>e1\nline2\nline3");
        $this->assertTrue($buffer->isOnFirstLine());

        $this->setBufferState($buffer, "line1\n<cursor>line2\nline3");
        $this->assertFalse($buffer->isOnFirstLine());

        $this->setBufferState($buffer, "line1\nline2\n<cursor>line3");
        $this->assertFalse($buffer->isOnFirstLine());
    }

    public function testIsOnLastLine(): void
    {
        $buffer = new Buffer();
        $buffer->setText("line1\nline2\nline3");

        $this->setBufferState($buffer, "line1\nline2\n<cursor>line3");
        $this->assertTrue($buffer->isOnLastLine());

        $this->setBufferState($buffer, "line1\nline2\nlin<cursor>e3");
        $this->assertTrue($buffer->isOnLastLine());

        $this->setBufferState($buffer, "<cursor>line1\nline2\nline3");
        $this->assertFalse($buffer->isOnLastLine());

        $this->setBufferState($buffer, "line1\n<cursor>line2\nline3");
        $this->assertFalse($buffer->isOnLastLine());
    }

    public function testMoveToPreviousLine(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "line1\nline2\n<cursor>line3");

        // Move to line2
        $this->assertTrue($buffer->moveToPreviousLine());
        $this->assertSame(1, $buffer->getCurrentLineNumber());
        $this->assertSame(6, $buffer->getCursor());

        // Move to line1
        $this->assertTrue($buffer->moveToPreviousLine());
        $this->assertSame(0, $buffer->getCurrentLineNumber());
        $this->assertSame(0, $buffer->getCursor());

        // Can't move up from first line
        $this->assertFalse($buffer->moveToPreviousLine());
        $this->assertSame(0, $buffer->getCurrentLineNumber());
    }

    public function testMoveToNextLine(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "<cursor>line1\nline2\nline3");

        // Move to line2
        $this->assertTrue($buffer->moveToNextLine());
        $this->assertSame(1, $buffer->getCurrentLineNumber());
        $this->assertSame(6, $buffer->getCursor());

        // Move to line3
        $this->assertTrue($buffer->moveToNextLine());
        $this->assertSame(2, $buffer->getCurrentLineNumber());
        $this->assertSame(12, $buffer->getCursor());

        // Can't move down from last line
        $this->assertFalse($buffer->moveToNextLine());
        $this->assertSame(2, $buffer->getCurrentLineNumber());
    }

    public function testMoveToPreviousLineMaintainsColumn(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "short\nlonger<cursor> line\nline");

        // Move to line1, should maintain column 5
        $this->assertTrue($buffer->moveToPreviousLine());
        $this->assertSame(5, $buffer->getCursor()); // "short" has 5 chars, cursor at end
    }

    public function testMoveToNextLineMaintainsColumn(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "longe<cursor>r line\nshort\nend");

        // Move to "short", should maintain column 5 (at end since "short" is 5 chars)
        $this->assertTrue($buffer->moveToNextLine());
        $this->assertSame(17, $buffer->getCursor()); // 12 (start of "short") + 5 = 17
    }

    public function testMoveToPreviousLineWithShorterLine(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "abc\nabcdefg<cursor>h\nxyz");

        // Move to "abc", column 7 is beyond line length, should go to end
        $this->assertTrue($buffer->moveToPreviousLine());
        $this->assertSame(3, $buffer->getCursor()); // End of "abc"
    }

    public function testMoveToNextLineWithShorterLine(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "abcdefg<cursor>h\nabc\nxyz");

        // Move to "abc", column 7 is beyond line length, should go to end
        $this->assertTrue($buffer->moveToNextLine());
        $this->assertSame(12, $buffer->getCursor()); // End of "abc" (9 + 3)
    }

    public function testSingleLineNavigation(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'singl<cursor>e line');

        // Single line, on both first and last line
        $this->assertTrue($buffer->isOnFirstLine());
        $this->assertTrue($buffer->isOnLastLine());

        // Can't move up or down
        $this->assertFalse($buffer->moveToPreviousLine());
        $this->assertFalse($buffer->moveToNextLine());
    }

    public function testDeletePreviousWordRemovesEmptyParens(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'array_map(<cursor>)');

        $buffer->deletePreviousWord();

        $this->assertBufferState('<cursor>', $buffer);
    }

    public function testDeletePreviousWordRemovesEmptyBrackets(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$arr[<cursor>]');

        $buffer->deletePreviousWord();

        $this->assertBufferState('$<cursor>', $buffer);
    }

    public function testDeletePreviousWordRemovesEmptyBraces(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'function foo() {<cursor>}');

        $buffer->deletePreviousWord();

        $this->assertBufferState('function <cursor>', $buffer);
    }

    public function testDeletePreviousWordInsideBracketsWithContent(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'foo(<cursor>bar)');

        $buffer->deletePreviousWord();

        // Cursor is NOT between empty brackets (there's "bar" after),
        // so normal deletion: deletes from word start (0) to cursor (4) = "foo("
        $this->assertBufferState('<cursor>bar)', $buffer);
    }

    public function testDeletePreviousTokenRemovesEmptyParens(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'test(<cursor>)');

        $buffer->deletePreviousToken();

        $this->assertBufferState('<cursor>', $buffer);
    }

    public function testDeletePreviousTokenFromMethodCall(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$obj->method(<cursor>)');

        $buffer->deletePreviousToken();

        $this->assertBufferState('$obj-><cursor>', $buffer);
    }

    public function testDeletePreviousTokenKeepsNonMatchingBrackets(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$foo->bar<cursor>(');

        $buffer->deletePreviousToken();

        $this->assertBufferState('$foo-><cursor>(', $buffer);
    }

    public function testDeletePreviousWordDoesNotRemoveMismatchedBrackets(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'test<cursor>(]');

        $buffer->deletePreviousWord();

        $this->assertBufferState('<cursor>(]', $buffer);
    }

    public function testDeletePreviousTokenFromArrayAccess(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$array[<cursor>key]');

        $buffer->deletePreviousToken();

        // Should delete "[" only (it's a token)
        $this->assertBufferState('$array<cursor>key]', $buffer);
    }

    public function testMoveCursorRightOverZwjEmoji(): void
    {
        $buffer = new Buffer();
        // 👨‍👩‍👧‍👦 = U+1F468 U+200D U+1F469 U+200D U+1F467 U+200D U+1F466 (7 code points, 1 grapheme)
        $this->setBufferState($buffer, "a<cursor>\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}b");

        $moved = $buffer->moveCursorRight();

        $this->assertSame(7, $moved);
        $this->assertBufferState("a\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}<cursor>b", $buffer);
    }

    public function testMoveCursorLeftOverZwjEmoji(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "a\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}<cursor>b");

        $moved = $buffer->moveCursorLeft();

        $this->assertSame(7, $moved);
        $this->assertBufferState("a<cursor>\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}b", $buffer);
    }

    public function testMoveCursorOverFlagEmoji(): void
    {
        $buffer = new Buffer();
        // 🇺🇸 = U+1F1FA U+1F1F8 (2 code points, 1 grapheme)
        $this->setBufferState($buffer, "x<cursor>\u{1F1FA}\u{1F1F8}y");

        $buffer->moveCursorRight();

        $this->assertBufferState("x\u{1F1FA}\u{1F1F8}<cursor>y", $buffer);
    }

    public function testMoveCursorOverCombiningMark(): void
    {
        $buffer = new Buffer();
        // é = e + combining acute accent (2 code points, 1 grapheme)
        $this->setBufferState($buffer, "a<cursor>e\u{0301}b");

        $buffer->moveCursorRight();

        $this->assertBufferState("ae\u{0301}<cursor>b", $buffer);
    }

    public function testMoveCursorOverSkinToneModifier(): void
    {
        $buffer = new Buffer();
        // 👋🏽 = U+1F44B U+1F3FD (2 code points, 1 grapheme)
        $this->setBufferState($buffer, "a<cursor>\u{1F44B}\u{1F3FD}b");

        $buffer->moveCursorRight();

        $this->assertBufferState("a\u{1F44B}\u{1F3FD}<cursor>b", $buffer);
    }

    public function testDeleteBackwardZwjEmoji(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "a\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}<cursor>b");

        $buffer->deleteBackward();

        $this->assertBufferState('a<cursor>b', $buffer);
    }

    public function testDeleteForwardZwjEmoji(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "a<cursor>\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}b");

        $buffer->deleteForward();

        $this->assertBufferState('a<cursor>b', $buffer);
    }

    public function testDeleteBackwardCombiningMark(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "ae\u{0301}<cursor>b");

        $buffer->deleteBackward();

        $this->assertBufferState('a<cursor>b', $buffer);
    }

    public function testDeleteForwardFlagEmoji(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "x<cursor>\u{1F1FA}\u{1F1F8}y");

        $buffer->deleteForward();

        $this->assertBufferState('x<cursor>y', $buffer);
    }

    public function testGetCharAfterCursorReturnsFullGrapheme(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "a<cursor>\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}b");

        $char = $buffer->getCharAfterCursor();

        $this->assertSame("\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}", $char);
    }

    public function testGetCharBeforeCursorReturnsFullGrapheme(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "a\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}<cursor>b");

        $char = $buffer->getCharBeforeCursor();

        $this->assertSame("\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}", $char);
    }

    public function testGetCharAfterCursorCombiningMark(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "a<cursor>e\u{0301}b");

        $char = $buffer->getCharAfterCursor();

        $this->assertSame("e\u{0301}", $char);
    }

    public function testMoveCursorRightMultipleGraphemes(): void
    {
        $buffer = new Buffer();
        // Two flag emojis: 🇺🇸🇬🇧
        $this->setBufferState($buffer, "<cursor>\u{1F1FA}\u{1F1F8}\u{1F1EC}\u{1F1E7}");

        $buffer->moveCursorRight(2);

        $this->assertBufferState("\u{1F1FA}\u{1F1F8}\u{1F1EC}\u{1F1E7}<cursor>", $buffer);
    }

    public function testMoveCursorLeftMultipleGraphemes(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "\u{1F1FA}\u{1F1F8}\u{1F1EC}\u{1F1E7}<cursor>");

        $buffer->moveCursorLeft(2);

        $this->assertBufferState("<cursor>\u{1F1FA}\u{1F1F8}\u{1F1EC}\u{1F1E7}", $buffer);
    }

    public function testDeleteBackwardMultipleGraphemes(): void
    {
        $buffer = new Buffer();
        // a + 👋🏽 + 🇺🇸 + b
        $this->setBufferState($buffer, "a\u{1F44B}\u{1F3FD}\u{1F1FA}\u{1F1F8}<cursor>b");

        $buffer->deleteBackward(2);

        $this->assertBufferState('a<cursor>b', $buffer);
    }

    public function testAsciiUnchangedByGraphemeAwareness(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hel<cursor>lo');

        $buffer->moveCursorRight();
        $this->assertBufferState('hell<cursor>o', $buffer);

        $buffer->moveCursorLeft();
        $this->assertBufferState('hel<cursor>lo', $buffer);

        $this->assertSame('l', $buffer->getCharAfterCursor());
        $this->assertSame('l', $buffer->getCharBeforeCursor());

        $buffer->deleteBackward();
        $this->assertBufferState('he<cursor>lo', $buffer);

        $buffer->deleteForward();
        $this->assertBufferState('he<cursor>o', $buffer);
    }

    public function testSimpleMultibyteUnchanged(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello<cursor>世界');

        $this->assertSame('世', $buffer->getCharAfterCursor());

        $buffer->moveCursorRight();
        $this->assertBufferState('hello世<cursor>界', $buffer);

        $this->assertSame('界', $buffer->getCharAfterCursor());
        $this->assertSame('世', $buffer->getCharBeforeCursor());

        $buffer->deleteBackward();
        $this->assertBufferState('hello<cursor>界', $buffer);
    }
}
