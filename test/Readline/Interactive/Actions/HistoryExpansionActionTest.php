<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Actions;

use PHPUnit\Framework\MockObject\MockObject;
use Psy\Readline\Interactive\Actions\HistoryExpansionAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class HistoryExpansionActionTest extends TestCase
{
    use BufferAssertionTrait;

    private History $history;
    private HistoryExpansionAction $action;
    private Buffer $buffer;
    /** @var Terminal&MockObject */
    private Terminal $terminal;
    /** @var Readline&MockObject */
    private Readline $readline;

    protected function setUp(): void
    {
        $this->history = new History();
        $this->action = new HistoryExpansionAction($this->history);
        $this->buffer = new Buffer();
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);
    }

    public function testDoubleExclamationExpandsToPreviousCommand()
    {
        $this->history->add('echo "hello world"');

        $this->setBufferState($this->buffer, '!!<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('echo "hello world"<cursor>', $this->buffer);
    }

    public function testMultilineExpansionSetsBufferCorrectly()
    {
        $multiline = "function test() {\n    return true;\n}";
        $this->history->add($multiline);

        $this->setBufferState($this->buffer, '!!<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState("function test() {\n    return true;\n}<cursor>", $this->buffer);
    }

    public function testExclamationDollarExpandsToLastArgument()
    {
        $this->history->add('echo "foo" "bar" "baz"');

        $this->setBufferState($this->buffer, '!$<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('"baz"<cursor>', $this->buffer);
    }

    public function testExclamationCaretExpandsToFirstArgument()
    {
        $this->history->add('echo "foo" "bar" "baz"');

        $this->setBufferState($this->buffer, '!^<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('"foo"<cursor>', $this->buffer);
    }

    public function testExclamationStarExpandsToAllArguments()
    {
        $this->history->add('echo "foo" "bar" "baz"');

        $this->setBufferState($this->buffer, '!*<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('"foo" "bar" "baz"<cursor>', $this->buffer);
    }

    public function testExpandsInMiddleOfLine()
    {
        $this->history->add('doc DateTime');

        $this->setBufferState($this->buffer, 'show !$<cursor>; dump "test"');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('show DateTime<cursor>; dump "test"', $this->buffer);
    }

    public function testPsyshCommandWithCodeArgument()
    {
        $this->history->add('ls $user->name');

        $this->setBufferState($this->buffer, 'doc !$<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('doc $user->name<cursor>', $this->buffer);
    }

    public function testPsyshCommandWithOptions()
    {
        $this->history->add('ls -al $foo->bar()');

        $this->setBufferState($this->buffer, 'dump !$<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('dump $foo->bar()<cursor>', $this->buffer);
    }

    public function testShowCommand()
    {
        $this->history->add('show SomeClass::someMethod');

        $this->setBufferState($this->buffer, 'doc !$<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('doc SomeClass::someMethod<cursor>', $this->buffer);
    }

    public function testMethodCallArguments()
    {
        $this->history->add('$user->getName($foo, $bar)');

        $this->setBufferState($this->buffer, 'echo !$<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('echo $bar<cursor>', $this->buffer);
    }

    public function testNoExpansionWhenNoHistory()
    {
        $this->setBufferState($this->buffer, '!!<cursor>');

        $this->terminal->expects($this->once())->method('bell');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('!!<cursor>', $this->buffer);
    }

    public function testNoExpansionWhenNotAtPattern()
    {
        $this->history->add('echo "test"');

        $this->setBufferState($this->buffer, 'echo <cursor>!!test'); // cursor not after !!

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('echo <cursor>!!test', $this->buffer);
    }

    public function testNoExpansionWhenPartOfWord()
    {
        $this->history->add('echo "test"');

        // !! inside a variable name shouldn't expand
        $this->setBufferState($this->buffer, '$foo!!<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('$foo!!<cursor>', $this->buffer);
    }

    public function testMultipleArgumentsWithFirstArgument()
    {
        $this->history->add('function_call($first, $second, $third)');

        $this->setBufferState($this->buffer, 'echo !^<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('echo $first<cursor>', $this->buffer);
    }

    public function testAllArgumentsExcludesCommand()
    {
        $this->history->add('var_dump($x, $y, $z)');

        $this->setBufferState($this->buffer, 'echo !*<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        // Should get all arguments, but not var_dump itself
        $this->assertBufferState('echo $x $y $z<cursor>', $this->buffer);
    }

    public function testComplexPHPExpression()
    {
        $this->history->add('$result = $user->getAddress()->getCity()');

        $this->setBufferState($this->buffer, 'echo !!<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('echo $result = $user->getAddress()->getCity()<cursor>', $this->buffer);
    }

    public function testPsyshCommandWithLongOptions()
    {
        $this->history->add('show --all --no-inherit MyClass');

        $this->setBufferState($this->buffer, 'doc !$<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('doc MyClass<cursor>', $this->buffer);
    }

    public function testQuotedStringsInArguments()
    {
        $this->history->add('echo "hello world" \'foo bar\'');

        $this->setBufferState($this->buffer, 'test !$<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('test \'foo bar\'<cursor>', $this->buffer);
    }

    public function testDoubleExclamationAfterMultibyteText()
    {
        $this->history->add('echo "hello"');

        // Multibyte character before !! pattern
        $this->setBufferState($this->buffer, 'é !!<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('é echo "hello"<cursor>', $this->buffer);
    }

    public function testLastArgAfterEmoji()
    {
        $this->history->add('doc DateTime');

        $this->setBufferState($this->buffer, '👍 !$<cursor>');

        $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('👍 DateTime<cursor>', $this->buffer);
    }

    public function testDetectExpansionWithMultibytePrecedingText()
    {
        // Verify detectExpansion correctly finds !! after multibyte chars
        $result = $this->action->detectExpansion('é !!', 4);

        $this->assertNotNull($result);
        $this->assertEquals('!!', $result['pattern']);
        $this->assertEquals(2, $result['start']);
        $this->assertEquals(4, $result['end']);
    }

    public function testDetectExpansionWordBoundaryWithMultibyte()
    {
        // !! preceded by alphanumeric should not match, even after multibyte
        $result = $this->action->detectExpansion('é foo!!', 7);

        $this->assertNull($result);
    }

    public function testDetectExpansionWordBoundaryWithAdjacentUnicodeLetter()
    {
        // é directly adjacent to !! Unicode letter should count as word char
        $result = $this->action->detectExpansion('é!!', 3);

        $this->assertNull($result);
    }
}
