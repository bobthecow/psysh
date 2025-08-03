<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Configuration;
use Psy\Exception\BreakException;
use Psy\Exception\ParseErrorException;
use Psy\Shell;
use Psy\TabCompletion\Matcher\ClassMethodsMatcher;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
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

    /**
     * @group isolation-fail
     */
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

    /**
     * @group isolation-fail
     */
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

    /**
     * @group isolation-fail
     */
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
        $config = $this->getConfig(['configFile' => __DIR__.'/fixtures/empty.php']);

        $shell = new Shell($config);
        $this->assertEmpty($shell->getIncludes());
        $shell->setIncludes(['foo', 'bar', 'baz']);
        $this->assertSame(['foo', 'bar', 'baz'], $shell->getIncludes());
    }

    public function testIncludesConfig()
    {
        $config = $this->getConfig([
            'defaultIncludes' => ['/file.php'],
            'configFile'      => __DIR__.'/fixtures/empty.php',
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

    /**
     * @group isolation-fail
     */
    public function testRenderingExceptions()
    {
        $shell = new Shell($this->getConfig());
        $output = $this->getOutput();
        $stream = $output->getStream();
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

        $expected = 'PARSE ERROR  PHP Parse error: message in test/ShellTest.php on line 236.';
        $this->assertSame($expected, \trim($streamContents));
    }

    /**
     * @dataProvider notSoBadErrors
     *
     * @group isolation-fail
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

    /**
     * @group isolation-fail
     */
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

    /**
     * @group isolation-fail
     */
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

    /**
     * @group isolation-fail
     */
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

    /**
     * @group isolation-fail
     */
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
     *
     * @group isolation-fail
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
     *
     * @group isolation-fail
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

    /**
     * @dataProvider getRenderedExceptions
     *
     * @group isolation-fail
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
     *
     * @group isolation-fail
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

    /**
     * @group isolation-fail
     */
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
     *
     * @group isolation-fail
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
     *
     * @group isolation-fail
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

    public function commandsToHas()
    {
        return [
            ['help', true],
            ['help help', true],
            ['"help"', false],
            ['"help help"', false],
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
            'configDir'  => $dir,
            'dataDir'    => $dir,
            'runtimeDir' => $dir,
            'colorMode'  => Configuration::COLOR_MODE_FORCED,
        ];

        return new Configuration(\array_merge($defaults, $config));
    }

    /**
     * @group isolation-fail
     */
    public function testStrictTypesExecute()
    {
        $shell = new Shell($this->getConfig(['strictTypes' => false]));
        $shell->setOutput($this->getOutput());
        $shell->execute('(function(): int { return 1.1; })()', true);
        $this->assertTrue(true);
    }

    /**
     * @group isolation-fail
     */
    public function testLaxTypesExecute()
    {
        $this->expectException(\TypeError::class);

        $shell = new Shell($this->getConfig(['strictTypes' => true]));
        $shell->setOutput($this->getOutput());
        $shell->execute('(function(): int { return 1.1; })()', true);
    }
}
