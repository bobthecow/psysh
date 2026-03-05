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

use PHPUnit\Framework\MockObject\MockObject;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class ReadlineTest extends TestCase
{
    /** @var Terminal&MockObject */
    private Terminal $terminal;
    private Readline $readline;

    protected function setUp(): void
    {
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = new Readline($this->terminal);
    }

    public function testNewReadlineStartsInSingleLineMode(): void
    {
        $this->assertFalse($this->readline->isMultilineMode());
    }

    public function testEnterMultilineMode(): void
    {
        $this->readline->enterMultilineMode();

        $this->assertTrue($this->readline->isMultilineMode());
    }

    public function testExitMultilineMode(): void
    {
        $this->readline->enterMultilineMode();
        $this->readline->exitMultilineMode();

        $this->assertFalse($this->readline->isMultilineMode());
    }

    public function testSetPrompt(): void
    {
        $this->readline->setPrompt('custom> ');

        $this->addToAssertionCount(1);
    }

    public function testSetMultilinePrompt(): void
    {
        $this->readline->setMultilinePrompt('.... ');

        $this->addToAssertionCount(1);
    }

    public function testCustomHistory(): void
    {
        $history = new History();
        $readline = new Readline($this->terminal, null, $history);

        $this->assertSame($history, $readline->getHistory());
    }

    public function testMultilineModeResetsOnExit(): void
    {
        $this->readline->enterMultilineMode();
        $this->readline->exitMultilineMode();

        $this->assertFalse($this->readline->isMultilineMode());

        $this->readline->enterMultilineMode();
        $this->assertTrue($this->readline->isMultilineMode());
    }

    public function testEnterAndExitMultilineMode(): void
    {
        $this->assertFalse($this->readline->isMultilineMode());

        $this->readline->enterMultilineMode();
        $this->assertTrue($this->readline->isMultilineMode());

        $this->readline->exitMultilineMode();
        $this->assertFalse($this->readline->isMultilineMode());
    }
}
