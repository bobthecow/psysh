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

use Psy\Readline\LegacyReadline;
use Psy\Readline\Readline;
use Symfony\Component\Console\Output\StreamOutput;

class LegacyReadlineTest extends \Psy\Test\TestCase
{
    public function testReadlineReturnsCompleteStatementAcrossMultiplePhysicalLines()
    {
        $readline = new LegacyReadline($this->newReadlineStub([
            'if (true) {',
            '    echo 1;',
            '}',
        ]));
        $readline->setBufferPrompt('... ');

        $this->assertSame("if (true) {\n    echo 1;\n}", $readline->readline('>>> '));
        $this->assertSame(['>>> ', '... ', '... '], $this->stubPrompts($readline));
    }

    public function testReadlineKeepsLegacyBackslashContinuation()
    {
        $readline = new LegacyReadline($this->newReadlineStub([
            '1 \\',
            '+ 1',
        ]));
        $readline->setBufferPrompt('... ');

        $this->assertSame("1 \n+ 1", $readline->readline('>>> '));
        $this->assertSame(['>>> ', '... '], $this->stubPrompts($readline));
    }

    public function testCtrlDWhileBuildingStatementClearsPartialInput()
    {
        $readline = new LegacyReadline($this->newReadlineStub([
            'if (true) {',
            false,
        ]));
        $readline->setBufferPrompt('... ');

        \ob_start();
        try {
            $this->assertSame('', $readline->readline('>>> '));
        } finally {
            \ob_end_clean();
        }

        $this->assertSame(['>>> ', '... '], $this->stubPrompts($readline));
    }

    public function testCtrlDWhileBuildingStatementWritesNewlineToConfiguredOutput()
    {
        $readline = new LegacyReadline($this->newReadlineStub([
            'if (true) {',
            false,
        ]));
        $readline->setBufferPrompt('... ');

        $stream = \fopen('php://memory', 'w+');
        $readline->setOutput(new StreamOutput($stream));

        \ob_start();
        try {
            $this->assertSame('', $readline->readline('>>> '));
            $this->assertSame('', \ob_get_contents());
        } finally {
            \ob_end_clean();
            \fclose($stream);
        }
    }

    public function testContinuationCommandReturnsCommandAndPreservesBuffer()
    {
        $shell = $this->createMock(\Psy\Shell::class);
        $shell->method('hasCommand')->willReturnCallback(fn (string $input) => \trim($input) === 'buffer');

        $readline = new LegacyReadline($this->newReadlineStub([
            'if (true) {',
            'buffer',
            '}',
        ]));
        $readline->setShell($shell);
        $readline->setBufferPrompt('... ');

        $this->assertSame('buffer', $readline->readline('>>> '));
        $this->assertSame(['if (true) {'], $readline->getBuffer());
        $this->assertSame(['>>> ', '... '], $this->stubPrompts($readline));

        $this->assertSame("if (true) {\n}", $readline->readline('>>> '));
        $this->assertSame([], $readline->getBuffer());
        $this->assertSame(['>>> ', '... ', '... '], $this->stubPrompts($readline));
    }

    public function testReadlineReturnsAppendedCompleteStatementWithoutPrompting()
    {
        $readline = new LegacyReadline($this->newReadlineStub([]));
        $readline->append("return 42;\n");

        $this->assertSame("return 42;\n", $readline->readline('>>> '));
        $this->assertSame([], $this->stubPrompts($readline));
    }

    /**
     * @param array<int, string|false> $inputs
     */
    private function newReadlineStub(array $inputs): Readline
    {
        return new class($inputs) implements Readline {
            /** @var array<int, string|false> */
            public array $inputs;
            /** @var array<int, string|null> */
            public array $prompts = [];

            /**
             * @param mixed $inputs
             */
            public function __construct($inputs = null, $_historySize = 0, $_eraseDups = false)
            {
                unset($_historySize, $_eraseDups);
                $this->inputs = $inputs;
            }

            public static function isSupported(): bool
            {
                return true;
            }

            public static function supportsBracketedPaste(): bool
            {
                return false;
            }

            public function addHistory(string $line): bool
            {
                return true;
            }

            public function clearHistory(): bool
            {
                return true;
            }

            public function listHistory(): array
            {
                return [];
            }

            public function readHistory(): bool
            {
                return true;
            }

            public function readline(?string $prompt = null)
            {
                $this->prompts[] = $prompt;

                return \array_shift($this->inputs);
            }

            public function redisplay()
            {
            }

            public function writeHistory(): bool
            {
                return true;
            }
        };
    }

    private function stubPrompts(LegacyReadline $readline): array
    {
        $property = new \ReflectionProperty($readline, 'readline');
        if (\PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property->getValue($readline)->prompts;
    }
}
