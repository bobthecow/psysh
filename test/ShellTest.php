<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\CodeCleaner\NoReturnValue;
use Psy\Configuration;
use Psy\Exception\BreakException;
use Psy\Exception\ParseErrorException;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\InteractiveReadlineInterface;
use Psy\Shell;
use Psy\ShellAware;
use Psy\TabCompletion\Matcher\ClassMethodsMatcher;
use Psy\Test\Fixtures\FakeShell;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class ShellTest extends TestCase
{
    private $streams = [];

    /**
     * @after
     */
    public function closeOpenStreams()
    {
        foreach ($this->streams as $stream) {
            \fclose($stream);
        }
    }

    public function testScopeVariables()
    {
        $one = 'banana';
        $two = 123;
        $three = new \stdClass();
        $__psysh__ = 'ignore this';
        $_ = 'ignore this';
        $_e = 'ignore this';

        $shell = new Shell($this->getConfig());
        $shell->setScopeVariables(\compact('one', 'two', 'three', '__psysh__', '_', '_e', 'this'));

        $this->assertNotContains('__psysh__', $shell->getScopeVariableNames());
        $this->assertSame(['one', 'two', 'three', '_'], $shell->getScopeVariableNames());
        $this->assertSame('banana', $shell->getScopeVariable('one'));
        $this->assertSame(123, $shell->getScopeVariable('two'));
        $this->assertSame($three, $shell->getScopeVariable('three'));
        $this->assertNull($shell->getScopeVariable('_'));

        $diff = $shell->getScopeVariablesDiff(['one' => $one, 'two' => 'not two']);
        $this->assertSame(['two' => $two, 'three' => $three, '_' => null], $diff);

        $shell->setScopeVariables([]);
        $this->assertSame(['_'], $shell->getScopeVariableNames());

        $shell->setBoundObject($this);
        $this->assertSame(['_', 'this'], $shell->getScopeVariableNames());
        $this->assertSame($this, $shell->getScopeVariable('this'));
        $this->assertSame(['_' => null], $shell->getScopeVariables(false));
        $this->assertSame(['_' => null, 'this' => $this], $shell->getScopeVariables());
    }

    public function testUnknownScopeVariablesThrowExceptions()
    {
        $this->expectException(\InvalidArgumentException::class);

        $shell = new Shell($this->getConfig());
        $shell->setScopeVariables(['foo' => 'FOO', 'bar' => 1]);
        $shell->getScopeVariable('baz');

        $this->fail();
    }

    public function testIncludesWithScopeVariables()
    {
        $one = 'banana';
        $two = 123;
        $three = new \stdClass();
        $__psysh__ = 'ignore this';
        $_ = 'ignore this';
        $_e = 'ignore this';

        $config = $this->getConfig(['usePcntl' => false]);

        $shell = new Shell($config);
        $shell->setScopeVariables(\compact('one', 'two', 'three', '__psysh__', '_', '_e', 'this'));
        $shell->addInput('exit', true);

        // This is super slow and we shouldn't do this :(
        $shell->run(null, $this->getOutput());

        $this->assertNotContains('__psysh__', $shell->getScopeVariableNames());
        $this->assertArrayEquals(['one', 'two', 'three', '_'], $shell->getScopeVariableNames());
        $this->assertSame('banana', $shell->getScopeVariable('one'));
        $this->assertSame(123, $shell->getScopeVariable('two'));
        $this->assertSame($three, $shell->getScopeVariable('three'));
        $this->assertNull($shell->getScopeVariable('_'));
    }

    protected function assertArrayEquals(array $expected, array $actual, $message = '')
    {
        if (\method_exists($this, 'assertSameCanonicalizing')) {
            return $this->assertSameCanonicalizing($expected, $actual, $message);
        }

        \sort($expected);
        \sort($actual);

        $this->assertSame($expected, $actual, $message);
    }

    public function testNonInteractiveDoesNotUpdateContext()
    {
        $config = $this->getConfig([
            'usePcntl'        => false,
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $shell = new Shell($config);

        $input = $this->getInput('');

        $shell->addInput('$var=5;', true);
        $shell->addInput('exit', true);

        // This is still super slow and we shouldn't do this :(
        $shell->run($input, $this->getOutput());

        $this->assertNotContains('var', $shell->getScopeVariableNames());
    }

    public function testNonInteractiveRawOutput()
    {
        $config = $this->getConfig([
            'usePcntl'        => false,
            'rawOutput'       => true,
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $shell = new Shell($config);

        $input = $this->getInput('');

        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell->setOutput($output);

        $shell->addInput('$foo = "bar"', true);
        $shell->addInput('exit', true);

        // Sigh
        $shell->run($input, $output);

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        // There shouldn't be a welcome message with raw output
        $this->assertStringNotContainsString('Justin Hileman', $streamContents);
        $this->assertStringNotContainsString(\PHP_VERSION, $streamContents);
        $this->assertStringNotContainsString(Shell::VERSION, $streamContents);

        // There shouldn't be an exit message with non-interactive input
        $this->assertStringNotContainsString('Goodbye', $streamContents);
        $this->assertStringNotContainsString('Exiting', $streamContents);
    }

    public function testIncludes()
    {
        $config = $this->getConfig(['configFile' => __DIR__.'/Fixtures/empty.php']);

        $shell = new Shell($config);
        $this->assertEmpty($shell->getIncludes());
        $shell->setIncludes(['foo', 'bar', 'baz']);
        $this->assertSame(['foo', 'bar', 'baz'], $shell->getIncludes());
    }

    public function testIncludesConfig()
    {
        $config = $this->getConfig([
            'defaultIncludes' => ['/file.php'],
            'configFile'      => __DIR__.'/Fixtures/empty.php',
        ]);

        $shell = new Shell($config);

        $includes = $shell->getIncludes();
        $this->assertSame('/file.php', $includes[0]);
    }

    public function testAddMatchersViaConfig()
    {
        $shell = new FakeShell();
        $matcher = new ClassMethodsMatcher();

        $config = $this->getConfig([
            'matchers' => [$matcher],
        ]);
        $config->setShell($shell);

        $this->assertSame([$matcher], $shell->matchers);
    }

    public function testAddMatchersViaConfigAfterShell()
    {
        $shell = new FakeShell();
        $matcher = new ClassMethodsMatcher();

        $config = $this->getConfig([]);
        $config->setShell($shell);
        $config->addMatchers([$matcher]);

        $this->assertSame([$matcher], $shell->matchers);
    }

    public function testCompletionSourcesViaConfigQueueUntilCompletionEngineInitialization()
    {
        $source = new class() implements \Psy\Completion\Source\SourceInterface {
            public function appliesToKind(int $kinds): bool
            {
                return true;
            }

            public function getCompletions(\Psy\Completion\AnalysisResult $analysis): array
            {
                return ['custom'];
            }
        };

        $readline = new class() implements InteractiveReadlineInterface, ShellAware {
            public ?\Psy\Completion\CompletionEngine $completionEngine = null;

            /** @phpstan-ignore-next-line (interface-required constructor params are unused in stub) */
            public function __construct($historyFile = null, $historySize = 0, $eraseDups = false)
            {
            }

            public static function isSupported(): bool
            {
                return true;
            }

            public static function supportsBracketedPaste(): bool
            {
                return true;
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
                return false;
            }

            public function redisplay()
            {
            }

            public function writeHistory(): bool
            {
                return true;
            }

            public function setRequireSemicolons(bool $require): void
            {
            }

            public function setTheme(\Psy\Output\Theme $theme): void
            {
            }

            public function setBracketedPaste(bool $enabled): void
            {
            }

            public function setUseSuggestions(bool $enabled): void
            {
            }

            public function setCompletionEngine(\Psy\Completion\CompletionEngine $completionEngine): void
            {
                $this->completionEngine = $completionEngine;
            }

            public function setOutput(OutputInterface $output, ?\Psy\Readline\Interactive\Terminal $terminal = null): void
            {
            }

            public function getHistory(): History
            {
                return new History();
            }

            public function setShell(Shell $shell): void
            {
            }

            public function setOutputWritten(bool $written): void
            {
            }
        };

        $config = $this->getConfig([
            'completionSources' => [$source],
            'useTabCompletion'  => true,
        ]);
        $config->setReadline($readline);

        $shell = new Shell($config);

        $pendingProperty = new \ReflectionProperty(Shell::class, 'pendingCompletionSources');
        if (\PHP_VERSION_ID < 80100) {
            $pendingProperty->setAccessible(true);
        }
        $this->assertSame([$source], $pendingProperty->getValue($shell));

        $shell->boot();

        $method = new \ReflectionMethod(Shell::class, 'initializeCompletionEngine');
        if (\PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $method->invoke($shell);

        $this->assertNotNull($readline->completionEngine);
        $this->assertSame([], $pendingProperty->getValue($shell));

        $sourcesProperty = new \ReflectionProperty(\Psy\Completion\CompletionEngine::class, 'sources');
        if (\PHP_VERSION_ID < 80100) {
            $sourcesProperty->setAccessible(true);
        }
        $sources = $sourcesProperty->getValue($readline->completionEngine);
        $this->assertTrue(\in_array($source, $sources, true));
    }

    public function testBootConfiguresInteractiveReadline()
    {
        $readline = new class() implements InteractiveReadlineInterface, ShellAware {
            public bool $shellWasSet = false;
            public ?bool $requireSemicolons = null;
            public ?bool $bracketedPaste = null;
            public ?\Psy\Output\Theme $theme = null;
            public ?OutputInterface $output = null;

            /** @phpstan-ignore-next-line (interface-required constructor params are unused in stub) */
            public function __construct($historyFile = null, $historySize = 0, $eraseDups = false)
            {
            }

            public static function isSupported(): bool
            {
                return true;
            }

            public static function supportsBracketedPaste(): bool
            {
                return true;
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
                return false;
            }

            public function redisplay()
            {
            }

            public function writeHistory(): bool
            {
                return true;
            }

            public function setRequireSemicolons(bool $require): void
            {
                $this->requireSemicolons = $require;
            }

            public function setTheme(\Psy\Output\Theme $theme): void
            {
                $this->theme = $theme;
            }

            public function setBracketedPaste(bool $enabled): void
            {
                $this->bracketedPaste = $enabled;
            }

            public function setUseSuggestions(bool $enabled): void
            {
            }

            public function setCompletionEngine(\Psy\Completion\CompletionEngine $completionEngine): void
            {
            }

            public function setOutput(OutputInterface $output, ?\Psy\Readline\Interactive\Terminal $terminal = null): void
            {
                $this->output = $output;
            }

            public function getHistory(): History
            {
                return new History();
            }

            public function setShell(Shell $shell): void
            {
                $this->shellWasSet = true;
            }

            public function setOutputWritten(bool $written): void
            {
            }
        };

        $config = $this->getConfig([
            'useBracketedPaste' => true,
            'requireSemicolons' => true,
        ]);
        $config->setReadline($readline);

        $runOutput = $this->getOutput();
        $shell = new Shell($config);
        $shell->setOutput($runOutput);
        $shell->boot();

        $this->assertTrue($readline->shellWasSet);
        $this->assertSame($config->requireSemicolons(), $readline->requireSemicolons);
        $this->assertSame($config->useBracketedPaste(), $readline->bracketedPaste);
        $this->assertSame($config->theme(), $readline->theme);
    }

    public function testRenderingExceptions()
    {
        $shell = new Shell($this->getConfig());
        $output = $this->getOutput();
        $stream = $output->getStream();
        $line = __LINE__ + 1;
        $e = new ParseErrorException('message', 13);

        $shell->setOutput($output);
        $shell->addCode('code');
        $this->assertTrue($shell->hasCode());
        $this->assertNotEmpty($shell->getCodeBuffer());

        $shell->writeException($e);

        $this->assertSame($e, $shell->getScopeVariable('_e'));
        $this->assertFalse($shell->hasCode());
        $this->assertEmpty($shell->getCodeBuffer());

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        $expected = 'PARSE ERROR  PHP Parse error: message in test/ShellTest.php on line '.$line.'.';
        $this->assertSame($expected, \trim($streamContents));
    }

    public function testGetInputMarksOutputWrittenForCommandOutput()
    {
        $readline = $this->getInteractiveReadline(['cmd', false]);
        $config = $this->getConfig();
        $config->setReadline($readline);

        $shell = new Shell($config);
        $shell->add(new class() extends Command {
            public function __construct()
            {
                parent::__construct('cmd');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $output->writeln('visible command output');

                return 0;
            }
        });

        $shell->setOutput($this->getOutput());
        $shell->getInput();

        $this->assertSame([true], $readline->outputWrittenCalls);
    }

    public function testGetInputLeavesOutputWrittenFalseWhenCommandDoesNotWriteOutput()
    {
        $readline = $this->getInteractiveReadline(['cmd', false]);
        $config = $this->getConfig();
        $config->setReadline($readline);

        $shell = new Shell($config);
        $shell->add(new class() extends Command {
            public function __construct()
            {
                parent::__construct('cmd');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        });

        $shell->setOutput($this->getOutput());
        $shell->getInput();

        $this->assertSame([false], $readline->outputWrittenCalls);
    }

    /**
     * @dataProvider notSoBadErrors
     */
    public function testReportsErrors($errno, $label)
    {
        $shell = new Shell($this->getConfig());
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell->setOutput($output);

        $oldLevel = \error_reporting(\E_ALL);

        $shell->handleError($errno, 'wheee', null, 13);

        \error_reporting($oldLevel);

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        $this->assertStringContainsString($label, $streamContents);
        $this->assertStringContainsString('wheee', $streamContents);
        $this->assertStringContainsString('line 13', $streamContents);
    }

    public function notSoBadErrors()
    {
        return [
            [\E_WARNING, 'WARNING'],
            [\E_NOTICE, 'NOTICE'],
            [\E_CORE_WARNING, 'CORE WARNING'],
            [\E_COMPILE_WARNING, 'COMPILE WARNING'],
            [\E_USER_WARNING, 'USER WARNING'],
            [\E_USER_NOTICE, 'USER NOTICE'],
            [\E_DEPRECATED, 'DEPRECATED'],
            [\E_USER_DEPRECATED, 'USER DEPRECATED'],
        ];
    }

    /**
     * @dataProvider badErrors
     */
    public function testThrowsBadErrors($errno)
    {
        $this->expectException(\Psy\Exception\ErrorException::class);

        $shell = new Shell($this->getConfig());
        $shell->handleError($errno, 'wheee', null, 13);

        $this->fail();
    }

    public function badErrors()
    {
        return [
            [\E_ERROR],
            [\E_PARSE],
            [\E_CORE_ERROR],
            [\E_COMPILE_ERROR],
            [\E_USER_ERROR],
            [\E_RECOVERABLE_ERROR],
        ];
    }

    public function testVersion()
    {
        $shell = new Shell($this->getConfig());

        $this->assertInstanceOf(Application::class, $shell);
        $this->assertStringContainsString(Shell::VERSION, $shell->getVersion());
        $this->assertStringContainsString(\PHP_VERSION, $shell->getVersion());
        $this->assertStringContainsString(\PHP_SAPI, $shell->getVersion());
    }

    public function testGetVersionHeader()
    {
        $header = Shell::getVersionHeader(false);

        $this->assertStringContainsString(Shell::VERSION, $header);
        $this->assertStringContainsString(\PHP_VERSION, $header);
        $this->assertStringContainsString(\PHP_SAPI, $header);
    }

    public function testCodeBuffer()
    {
        $shell = new Shell($this->getConfig());

        $shell->addCode('class');
        $this->assertNull($shell->flushCode());
        $this->assertTrue($shell->hasCode());

        $shell->addCode('a');
        $this->assertNull($shell->flushCode());
        $this->assertTrue($shell->hasCode());

        $shell->addCode('{}');
        $code = $shell->flushCode();
        $this->assertFalse($shell->hasCode());
        $code = \preg_replace('/\s+/', ' ', $code);
        $this->assertNotNull($code);
        $this->assertSame('class a { } return new \\Psy\\CodeCleaner\\NoReturnValue();', $code);
    }

    public function testKeepCodeBufferOpen()
    {
        $shell = new Shell($this->getConfig());

        $shell->addCode('1 \\');
        $this->assertNull($shell->flushCode());
        $this->assertTrue($shell->hasCode());

        $shell->addCode('+ 1 \\');
        $this->assertNull($shell->flushCode());
        $this->assertTrue($shell->hasCode());

        $shell->addCode('+ 1');
        $code = $shell->flushCode();
        $this->assertFalse($shell->hasCode());
        $code = \preg_replace('/\s+/', ' ', $code);
        $this->assertNotNull($code);
        $this->assertSame('return 1 + 1 + 1;', $code);
    }

    public function testCodeBufferThrowsParseExceptions()
    {
        $this->expectException(ParseErrorException::class);

        $shell = new Shell($this->getConfig());
        $shell->addCode('this is not valid');
        $shell->flushCode();

        $this->fail();
    }

    public function testClosuresSupport()
    {
        $shell = new Shell($this->getConfig());
        $code = '$test = function () {}';
        $shell->addCode($code);
        $shell->flushCode();
        $code = '$test()';
        $shell->addCode($code);
        $this->assertSame($shell->flushCode(), 'return $test();');
    }

    public function testWriteStdout()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig());
        $shell->setOutput($output);

        $shell->writeStdout("{{stdout}}\n");

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        $this->assertSame('{{stdout}}'.\PHP_EOL, $streamContents);
    }

    public function testWriteStdoutWithoutNewline()
    {
        $this->markTestSkipped('This test won\'t work on CI without overriding pipe detection');

        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig());
        $shell->setOutput($output);

        $shell->writeStdout('{{stdout}}');

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        $this->assertSame('{{stdout}}<aside>⏎</aside>'.\PHP_EOL, $streamContents);
    }

    public function testWriteStdoutRawOutputWithoutNewline()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig(['rawOutput' => true]));
        $shell->setOutput($output);

        $shell->writeStdout('{{stdout}}');

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        $this->assertSame('{{stdout}}'.\PHP_EOL, $streamContents);
    }

    /**
     * @dataProvider getReturnValues
     */
    public function testWriteReturnValue($input, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig(['theme' => 'modern']));
        $shell->setOutput($output);

        $shell->writeReturnValue($input);
        \rewind($stream);
        $this->assertSame($expected, \stream_get_contents($stream));
    }

    /**
     * @dataProvider getReturnValues
     */
    public function testDoNotWriteReturnValueWhenQuiet($input, $expected)
    {
        $output = $this->getOutput();
        $output->setVerbosity(StreamOutput::VERBOSITY_QUIET);
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig(['theme' => 'modern']));
        $shell->setOutput($output);

        $shell->writeReturnValue($input);
        \rewind($stream);
        $this->assertSame('', \stream_get_contents($stream));
    }

    public function getReturnValues()
    {
        return [
            ['{{return value}}', "<whisper>= </whisper>\"\033[32m{{return value}}\033[39m\"".\PHP_EOL],
            [1, "<whisper>= </whisper>\033[35m1\033[39m".\PHP_EOL],
        ];
    }

    public function testSemicolonsSuppressReturnSuppressesDisplay()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        $shell->addCode('$foo = 123;');

        $shell->writeReturnValue(123);
        \rewind($stream);
        $this->assertSame('', \stream_get_contents($stream));
    }

    public function testSemicolonsSuppressReturnCapturesDollarUnderscore()
    {
        $output = $this->getOutput();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        $shell->addCode('$foo = 123;');

        // writeReturnValue still sets $_ even when suppressed
        $shell->writeReturnValue(123);
        $this->assertSame(123, $shell->getScopeVariable('_'));
    }

    public function testSemicolonsSuppressReturnWithoutSemicolon()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        // No trailing semicolon — should still display
        $shell->addCode('$foo = 123');

        $shell->writeReturnValue(123);
        \rewind($stream);
        $this->assertNotEmpty(\stream_get_contents($stream));
    }

    public function testSemicolonsSuppressReturnWithTrailingComment()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        $shell->addCode('$foo = 123; // hush');

        $shell->writeReturnValue(123);
        \rewind($stream);
        $this->assertSame('', \stream_get_contents($stream));
    }

    public function testSemicolonsSuppressReturnDisabledByDefault()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => false,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        // With the feature disabled, semicolons don't suppress
        $shell->addCode('$foo = 123;');

        $shell->writeReturnValue(123);
        \rewind($stream);
        $this->assertNotEmpty(\stream_get_contents($stream));
    }

    public function testSemicolonsSuppressReturnWithRequireSemicolons()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'requireSemicolons'        => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        // Single semicolon is required, should still display
        $shell->addCode('$foo = 123;');

        $shell->writeReturnValue(123);
        \rewind($stream);
        $this->assertNotEmpty(\stream_get_contents($stream));
    }

    /**
     * @dataProvider getDoubleSemicolonInputs
     */
    public function testSemicolonsSuppressReturnWithDoubleSemicolon(string $input)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'requireSemicolons'        => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        $shell->addCode($input);

        $shell->writeReturnValue(123);
        \rewind($stream);
        $this->assertSame('', \stream_get_contents($stream));
    }

    public function getDoubleSemicolonInputs()
    {
        return [
            'adjacent'        => ['$foo = 123;;'],
            'space separated' => ['$foo = 123; ;'],
            'extra spaces'    => ['$foo = 123;   ;'],
            'tab separated'   => ["\$foo = 123;\t;"],
            'line comment'    => ['$foo = 123;; // hush'],
            'hash comment'    => ['$foo = 123;; # hush'],
        ];
    }

    public function testSemicolonsSuppressReturnWithControlStructure()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        // Trailing semicolon after a block — should suppress
        $shell->addCode('if (true) { 1; };');

        $shell->writeReturnValue(1);
        \rewind($stream);
        $this->assertSame('', \stream_get_contents($stream));
    }

    public function testSemicolonsSuppressReturnResetsAfterUse()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        // First: suppressed
        $shell->addCode('$foo = 1;');
        $shell->writeReturnValue(1);
        \rewind($stream);
        $this->assertSame('', \stream_get_contents($stream));

        // Second: not suppressed (no trailing semicolon)
        \ftruncate($stream, 0);
        \rewind($stream);
        $shell->addCode('$bar = 2');
        $shell->writeReturnValue(2);
        \rewind($stream);
        $this->assertNotEmpty(\stream_get_contents($stream));
    }

    public function testSemicolonsSuppressReturnResetsAfterNoReturnValue()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $config = $this->getConfig([
            'semicolonsSuppressReturn' => true,
            'theme'                    => 'modern',
        ]);
        $shell = new Shell($config);
        $shell->setOutput($output);

        $shell->addCode('class A {};');
        $shell->writeReturnValue(new NoReturnValue());
        $shell->writeReturnValue(123);

        \rewind($stream);
        $this->assertNotEmpty(\stream_get_contents($stream));
    }

    /**
     * @dataProvider getRenderedExceptions
     */
    public function testWriteException($exception, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig(['theme' => 'compact']));
        $shell->setOutput($output);

        $shell->writeException($exception);
        \rewind($stream);
        $this->assertSame($expected, \stream_get_contents($stream));
    }

    /**
     * @dataProvider getRenderedExceptions
     */
    public function testWriteExceptionVerbose($exception, $expected)
    {
        $output = $this->getOutput();
        $output->setVerbosity(StreamOutput::VERBOSITY_VERBOSE);
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig(['theme' => 'compact']));
        $shell->setOutput($output);

        $shell->writeException($exception);
        \rewind($stream);
        $stdout = \stream_get_contents($stream);
        $this->assertStringStartsWith($expected, $stdout);
        $this->assertStringContainsString(\basename(__FILE__), $stdout);

        $lineCount = \count(\explode(\PHP_EOL, $stdout));
        $this->assertGreaterThan(4, $lineCount); // /shrug
    }

    public function getRenderedExceptions()
    {
        return [[
            new \Exception('{{message}}'),
            " Exception  {{message}}.\n",
        ]];
    }

    public function testWriteExceptionVerboseButNotReallyBecauseItIsABreakException()
    {
        $output = $this->getOutput();
        $output->setVerbosity(StreamOutput::VERBOSITY_VERBOSE);
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig(['theme' => 'compact']));
        $shell->setOutput($output);

        $shell->writeException(new BreakException('yeah'));
        \rewind($stream);

        $this->assertSame(" INFO  yeah.\n", \stream_get_contents($stream));
    }

    /**
     * @dataProvider getExceptionOutput
     */
    public function testCompactExceptionOutput($theme, $exception, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig(['theme' => $theme]));
        $shell->setOutput($output);

        $shell->writeException($exception);
        \rewind($stream);

        $this->assertSame($expected, \stream_get_contents($stream));
    }

    public function getExceptionOutput()
    {
        return [
            ['compact', new BreakException('break'), " INFO  break.\n"],
            ['modern', new BreakException('break'), "\n   INFO  break.\n\n"],
            ['compact', new \Exception('foo'), " Exception  foo.\n"],
            ['modern', new \Exception('bar'), "\n   Exception  bar.\n\n"],
        ];
    }

    /**
     * @dataProvider getExecuteValues
     */
    public function testShellExecute($input, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell = new Shell($this->getConfig());
        $shell->setOutput($output);
        $this->assertSame($expected, $shell->execute($input));
        \rewind($stream);
        $this->assertSame('', \stream_get_contents($stream));
    }

    public function getExecuteValues()
    {
        return [
            ['return 12', 12],
            ['"{{return value}}"', '{{return value}}'],
            ['1', 1],
        ];
    }

    public function testShellExecuteUsesNonInteractivePromptContext()
    {
        $dir = \tempnam(\sys_get_temp_dir(), 'psysh_shell_test_');
        \unlink($dir);

        $options = [
            'configFile'      => __DIR__.'/Fixtures/empty.php',
            'interactiveMode' => Configuration::INTERACTIVE_MODE_FORCED,
            'trustProject'    => Configuration::PROJECT_TRUST_PROMPT,
            'colorMode'       => Configuration::COLOR_MODE_FORCED,
            'configDir'       => $dir,
            'dataDir'         => $dir,
            'runtimeDir'      => $dir,
        ];

        $config = new class($options) extends Configuration {
            public ?bool $lastPromptInputInteractive = null;

            public function loadLocalConfigWithPrompt($input, $output): void
            {
                $this->lastPromptInputInteractive = $input->isInteractive();
            }
        };

        $shell = new Shell($config);
        $shell->setOutput($config->getOutput());

        // execute() returns the eval'd value, not an exit code
        $this->assertSame(2, $shell->execute('1 + 1'));
        $this->assertFalse($config->lastPromptInputInteractive);
    }

    /**
     * @dataProvider commandsToHas
     */
    public function testHasCommand($command, $has)
    {
        $shell = new Shell($this->getConfig());

        // :-/
        $refl = new \ReflectionClass(Shell::class);
        $method = $refl->getMethod('hasCommand');
        if (\PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $this->assertSame($method->invokeArgs($shell, [$command]), $has);
    }

    public function testPhpCommandCollisionFunctionIgnoresShellOnlySyntax()
    {
        $shell = new Shell($this->getConfig());

        $refl = new \ReflectionClass(Shell::class);
        $boot = $refl->getMethod('boot');
        $method = $refl->getMethod('getPhpCommandCollisionFunction');
        if (\PHP_VERSION_ID < 80100) {
            $boot->setAccessible(true);
            $method->setAccessible(true);
        }

        $boot->invoke($shell);

        $this->assertNull($method->invokeArgs($shell, ['help --help']));
    }

    public function commandsToHas()
    {
        return [
            ['help', true],
            ['help help', true],
            ['help ("help")', true],
            ['"help"', false],
            ['"help help"', false],
            ['copy', true],
            ['copy ($from, $to)', true],
            ['ls -al ', true],
            ['ls "-al" ', true],
            ['ls"-al"', false],
            [' q', true],
            ['   q  --help', true],
            ['"q"', false],
            ['"q",', false],
        ];
    }

    private function getInput($input)
    {
        $input = new StringInput($input);

        return $input;
    }

    private function getInteractiveReadline(array $inputs)
    {
        return new class($inputs) implements InteractiveReadlineInterface, ShellAware {
            private array $inputs;
            public array $outputWrittenCalls = [];

            /** @phpstan-ignore-next-line (interface-required constructor params are repurposed in stub) */
            public function __construct($historyFile = null, $historySize = 0, $eraseDups = false)
            {
                $this->inputs = \is_array($historyFile) ? $historyFile : [];
            }

            public static function isSupported(): bool
            {
                return true;
            }

            public static function supportsBracketedPaste(): bool
            {
                return true;
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
                if ($this->inputs === []) {
                    return false;
                }

                return \array_shift($this->inputs);
            }

            public function redisplay()
            {
            }

            public function writeHistory(): bool
            {
                return true;
            }

            public function setRequireSemicolons(bool $require): void
            {
            }

            public function setTheme(\Psy\Output\Theme $theme): void
            {
            }

            public function setBracketedPaste(bool $enabled): void
            {
            }

            public function setUseSuggestions(bool $enabled): void
            {
            }

            public function setCompletionEngine(\Psy\Completion\CompletionEngine $completionEngine): void
            {
            }

            public function setOutput(OutputInterface $output, ?\Psy\Readline\Interactive\Terminal $terminal = null): void
            {
            }

            public function getHistory(): History
            {
                return new History();
            }

            public function setShell(Shell $shell): void
            {
            }

            public function setOutputWritten(bool $written): void
            {
                $this->outputWrittenCalls[] = $written;
            }
        };
    }

    private function getOutput()
    {
        $stream = \fopen('php://memory', 'w+');
        $this->streams[] = $stream;

        $output = new StreamOutput($stream, StreamOutput::VERBOSITY_NORMAL, false);

        return $output;
    }

    private function getConfig(array $config = [])
    {
        // Mebbe there's a better way than this?
        $dir = \tempnam(\sys_get_temp_dir(), 'psysh_shell_test_');
        \unlink($dir);

        $defaults = [
            'configDir'    => $dir,
            'dataDir'      => $dir,
            'runtimeDir'   => $dir,
            'colorMode'    => Configuration::COLOR_MODE_FORCED,
            'trustProject' => true,
        ];

        return new Configuration(\array_merge($defaults, $config));
    }

    public function testStrictTypesExecute()
    {
        $shell = new Shell($this->getConfig(['strictTypes' => false]));
        $shell->setOutput($this->getOutput());
        $shell->execute('(function(): int { return 1.1; })()', true);
        $this->assertTrue(true);
    }

    /**
     * @group php-parser-v4-fail
     */
    public function testLaxTypesExecute()
    {
        $this->expectException(\TypeError::class);

        $shell = new Shell($this->getConfig(['strictTypes' => true]));
        $shell->setOutput($this->getOutput());
        $shell->execute('(function(): int { return 1.1; })()', true);
    }
}
