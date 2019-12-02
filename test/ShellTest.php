<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2019 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Configuration;
use Psy\Exception\ParseErrorException;
use Psy\Shell;
use Psy\TabCompletion\Matcher\ClassMethodsMatcher;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

class ShellTest extends \PHPUnit\Framework\TestCase
{
    private $streams = [];

    public function tearDown()
    {
        foreach ($this->streams as $stream) {
            \fclose($stream);
        }
    }

    public function testScopeVariables()
    {
        $one       = 'banana';
        $two       = 123;
        $three     = new \stdClass();
        $__psysh__ = 'ignore this';
        $_         = 'ignore this';
        $_e        = 'ignore this';

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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnknownScopeVariablesThrowExceptions()
    {
        $shell = new Shell($this->getConfig());
        $shell->setScopeVariables(['foo' => 'FOO', 'bar' => 1]);
        $shell->getScopeVariable('baz');
    }

    public function testIncludesWithScopeVariables()
    {
        $one       = 'banana';
        $two       = 123;
        $three     = new \stdClass();
        $__psysh__ = 'ignore this';
        $_         = 'ignore this';
        $_e        = 'ignore this';

        $config = $this->getConfig(['usePcntl' => false]);

        $shell = new Shell($config);
        $shell->setScopeVariables(\compact('one', 'two', 'three', '__psysh__', '_', '_e', 'this'));
        $shell->addInput('exit', true);

        // This is super slow and we shouldn't do this :(
        $shell->run(null, $this->getOutput());

        $this->assertNotContains('__psysh__', $shell->getScopeVariableNames());
        $this->assertArrayEquals(['one', 'two', 'three', '_', '_e'], $shell->getScopeVariableNames());
        $this->assertSame('banana', $shell->getScopeVariable('one'));
        $this->assertSame(123, $shell->getScopeVariable('two'));
        $this->assertSame($three, $shell->getScopeVariable('three'));
        $this->assertNull($shell->getScopeVariable('_'));
    }

    protected function assertArrayEquals(array $expected, array $actual, $message = '')
    {
        if (\method_exists($this, 'assertEqualsCanonicalizing')) {
            return $this->assertEqualsCanonicalizing($expected, $actual, $message);
        }

        sort($expected);
        sort($actual);

        return $this->assertEquals($expected, $actual, $message);
    }

    public function testNonInteractiveDoesNotUpdateContext()
    {
        $config = $this->getConfig(['usePcntl' => false]);
        $shell = new Shell($config);

        $input = $this->getInput('');
        $input->setInteractive(false);

        $shell->addInput('$var=5;', true);
        $shell->addInput('exit', true);

        // This is still super slow and we shouldn't do this :(
        $shell->run($input, $this->getOutput());

        $this->assertNotContains('var', $shell->getScopeVariableNames());
    }

    public function testNonInteractiveRawOutput()
    {
        $config = $this->getConfig(['usePcntl' => false, 'rawOutput' => true]);
        $shell = new Shell($config);

        $input = $this->getInput('');
        $input->setInteractive(false);

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
        $this->assertNotContains('Justin Hileman', $streamContents);
        $this->assertNotContains(PHP_VERSION, $streamContents);
        $this->assertNotContains(Shell::VERSION, $streamContents);

        // @todo prolly shouldn't have an exit message with raw output, either
        $this->assertContains('Goodbye', $streamContents);
    }

    public function testIncludes()
    {
        $config = $this->getConfig(['configFile' => __DIR__ . '/fixtures/empty.php']);

        $shell = new Shell($config);
        $this->assertEmpty($shell->getIncludes());
        $shell->setIncludes(['foo', 'bar', 'baz']);
        $this->assertSame(['foo', 'bar', 'baz'], $shell->getIncludes());
    }

    public function testIncludesConfig()
    {
        $config = $this->getConfig([
            'defaultIncludes' => ['/file.php'],
            'configFile'      => __DIR__ . '/fixtures/empty.php',
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

    public function testRenderingExceptions()
    {
        $shell  = new Shell($this->getConfig());
        $output = $this->getOutput();
        $stream = $output->getStream();
        $e      = new ParseErrorException('message', 13);

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

        $this->assertContains('PHP Parse error', $streamContents);
        $this->assertContains('message', $streamContents);
        $this->assertContains('line 13', $streamContents);
    }

    /**
     * @dataProvider notSoBadErrors
     */
    public function testReportsErrors($errno, $label)
    {
        $shell  = new Shell($this->getConfig());
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell->setOutput($output);

        $oldLevel = \error_reporting(E_ALL);

        $shell->handleError($errno, 'wheee', null, 13);

        \error_reporting($oldLevel);

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        $this->assertContains($label, $streamContents);
        $this->assertContains('wheee', $streamContents);
        $this->assertContains('line 13', $streamContents);
    }

    public function notSoBadErrors()
    {
        return [
            [E_WARNING, 'PHP Warning:'],
            [E_NOTICE, 'PHP Notice:'],
            [E_CORE_WARNING, 'PHP Warning:'],
            [E_COMPILE_WARNING, 'PHP Warning:'],
            [E_USER_WARNING, 'PHP Warning:'],
            [E_USER_NOTICE, 'PHP Notice:'],
            [E_DEPRECATED, 'PHP Deprecated:'],
            [E_USER_DEPRECATED, 'PHP Deprecated:'],
        ];
    }

    /**
     * @dataProvider badErrors
     * @expectedException \Psy\Exception\ErrorException
     */
    public function testThrowsBadErrors($errno)
    {
        $shell = new Shell($this->getConfig());
        $shell->handleError($errno, 'wheee', null, 13);
    }

    public function badErrors()
    {
        return [
            [E_ERROR],
            [E_PARSE],
            [E_CORE_ERROR],
            [E_COMPILE_ERROR],
            [E_USER_ERROR],
            [E_RECOVERABLE_ERROR],
        ];
    }

    public function testVersion()
    {
        $shell = new Shell($this->getConfig());

        $this->assertInstanceOf('Symfony\Component\Console\Application', $shell);
        $this->assertContains(Shell::VERSION, $shell->getVersion());
        $this->assertContains(PHP_VERSION, $shell->getVersion());
        $this->assertContains(PHP_SAPI, $shell->getVersion());
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

    /**
     * @expectedException \Psy\Exception\ParseErrorException
     */
    public function testCodeBufferThrowsParseExceptions()
    {
        $shell = new Shell($this->getConfig());
        $shell->addCode('this is not valid');
        $shell->flushCode();
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
        $shell  = new Shell($this->getConfig());
        $shell->setOutput($output);

        $shell->writeStdout("{{stdout}}\n");

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        $this->assertSame('{{stdout}}' . PHP_EOL, $streamContents);
    }

    public function testWriteStdoutWithoutNewline()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell  = new Shell($this->getConfig());
        $shell->setOutput($output);

        $shell->writeStdout('{{stdout}}');

        \rewind($stream);
        $streamContents = \stream_get_contents($stream);

        $this->assertSame('{{stdout}}<aside>⏎</aside>' . PHP_EOL, $streamContents);
    }

    /**
     * @dataProvider getReturnValues
     */
    public function testWriteReturnValue($input, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell  = new Shell($this->getConfig());
        $shell->setOutput($output);

        $shell->writeReturnValue($input);
        \rewind($stream);
        $this->assertEquals($expected, \stream_get_contents($stream));
    }

    /**
     * @dataProvider getReturnValues
     */
    public function testDoNotWriteReturnValueWhenQuiet($input, $expected)
    {
        $output = $this->getOutput();
        $output->setVerbosity(StreamOutput::VERBOSITY_QUIET);
        $stream = $output->getStream();
        $shell  = new Shell($this->getConfig());
        $shell->setOutput($output);

        $shell->writeReturnValue($input);
        \rewind($stream);
        $this->assertEquals('', \stream_get_contents($stream));
    }

    public function getReturnValues()
    {
        return [
            ['{{return value}}', "=> \"\033[32m{{return value}}\033[39m\"" . PHP_EOL],
            [1, "=> \033[35m1\033[39m" . PHP_EOL],
        ];
    }

    /**
     * @dataProvider getRenderedExceptions
     */
    public function testWriteException($exception, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell  = new Shell($this->getConfig());
        $shell->setOutput($output);

        $shell->writeException($exception);
        \rewind($stream);
        $this->assertSame($expected, \stream_get_contents($stream));
    }

    public function getRenderedExceptions()
    {
        return [
            [new \Exception('{{message}}'), "Exception with message '{{message}}'" . PHP_EOL],
        ];
    }

    /**
     * @dataProvider getExecuteValues
     */
    public function testShellExecute($input, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell  = new Shell($this->getConfig());
        $shell->setOutput($output);
        $this->assertEquals($expected, $shell->execute($input));
        \rewind($stream);
        $this->assertSame('', \stream_get_contents($stream));
    }

    public function getExecuteValues()
    {
        return [
            ['return 12', 12],
            ['"{{return value}}"', '{{return value}}'],
            ['1', '1'],
        ];
    }

    /**
     * @dataProvider commandsToHas
     */
    public function testHasCommand($command, $has)
    {
        $shell = new Shell($this->getConfig());

        // :-/
        $refl = new \ReflectionClass('Psy\\Shell');
        $method = $refl->getMethod('hasCommand');
        $method->setAccessible(true);

        $this->assertEquals($method->invokeArgs($shell, [$command]), $has);
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
        ];

        return new Configuration(\array_merge($defaults, $config));
    }
}
