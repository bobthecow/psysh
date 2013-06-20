<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Shell;
use Psy\Exception\ParseErrorException;
use Symfony\Component\Console\Output\StreamOutput;

class ShellTest extends \PHPUnit_Framework_TestCase
{
    private $streams = array();

    public function tearDown()
    {
        foreach ($this->streams as $stream) {
            fclose($stream);
        }
    }

    public function testScopeVariables()
    {
        $one   = 'banana';
        $two   = 123;
        $three = new \StdClass;
        $__psysh__ = 'ignore this';

        $shell = new Shell;
        $shell->setScopeVariables(compact('one', 'two', 'three', '__psysh__'));

        $this->assertNotContains('__psysh__', $shell->getScopeVariableNames());
        $this->assertEquals(array('one', 'two', 'three'), $shell->getScopeVariableNames());
        $this->assertEquals('banana', $shell->getScopeVariable('one'));
        $this->assertEquals(123, $shell->getScopeVariable('two'));
        $this->assertSame($three, $shell->getScopeVariable('three'));

        $shell->setScopeVariables(array());
        $this->assertEmpty($shell->getScopeVariableNames());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnknownScopeVariablesThrowExceptions()
    {
        $shell = new Shell;
        $shell->setScopeVariables(array('foo' => 'FOO', 'bar' => 1));
        $shell->getScopeVariable('baz');
    }

    public function testIncludes()
    {
        $shell = new Shell;
        $this->assertEmpty($shell->getIncludes());
        $shell->setIncludes(array('foo', 'bar', 'baz'));
        $this->assertEquals(array('foo', 'bar', 'baz'), $shell->getIncludes());
    }

    public function testRenderingExceptions()
    {
        $shell  = new Shell;
        $output = $this->getOutput();
        $stream = $output->getStream();
        $e      = new ParseErrorException('message', 13);

        $shell->addCode('code');
        $this->assertTrue($shell->hasCode());
        $this->assertNotEmpty($shell->getCodeBuffer());

        $shell->renderException($e, $output);

        $this->assertEquals(1, count($shell->getExceptions()));
        $this->assertSame($e, $shell->getLastException());
        $this->assertFalse($shell->hasCode());
        $this->assertEmpty($shell->getCodeBuffer());

        rewind($stream);
        $streamContents = stream_get_contents($stream);

        $this->assertContains('PHP Parse error', $streamContents);
        $this->assertContains('message', $streamContents);
        $this->assertContains('line 13', $streamContents);
    }

    public function testVersion()
    {
        $shell = new Shell;

        $this->assertInstanceOf('Symfony\Component\Console\Application', $shell);
        $this->assertContains(Shell::VERSION, $shell->getVersion());
        $this->assertContains(phpversion(), $shell->getVersion());
        $this->assertContains(php_sapi_name(), $shell->getVersion());
    }

    public function testCodeBuffer()
    {
        $shell = new Shell;

        $shell->addCode('class');
        $this->assertNull($shell->flushCode());
        $this->assertTrue($shell->hasCode());

        $shell->addCode('a');
        $this->assertNull($shell->flushCode());
        $this->assertTrue($shell->hasCode());

        $shell->addCode('{}');
        $code = $shell->flushCode();
        $this->assertFalse($shell->hasCode());
        $code = preg_replace('/\s+/', ' ', $code);
        $this->assertNotNull($code);
        $this->assertEquals('class a { }', $code);
    }

    /**
     * @expectedException \Psy\Exception\ParseErrorException
     */
    public function testCodeBufferThrowsParseExceptions()
    {
        $shell = new Shell;
        $shell->addCode('this is not valid');
        $shell->flushCode();
    }

    public function testWriteStdout()
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell  = new Shell;
        $shell->setOutput($output);

        $shell->writeStdout('{{stdout}}');

        rewind($stream);
        $streamContents = stream_get_contents($stream);

        $this->assertEquals('{{stdout}}'.PHP_EOL, $streamContents);
    }

    /**
     * @dataProvider getReturnValues
     */
    public function testWriteReturnValue($input, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell  = new Shell;
        $shell->setOutput($output);

        $shell->writeReturnValue($input);
        rewind($stream);
        $this->assertEquals($expected, stream_get_contents($stream));
    }

    public function getReturnValues()
    {
        return array(
            array('{{return value}}', '=> <return>"{{return value}}"</return>'.PHP_EOL),
            array(1, '=> <return>1</return>'.PHP_EOL),
        );
    }

    /**
     * @dataProvider getRenderedExceptions
     */
    public function testWriteException($exception, $expected)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $shell  = new Shell;
        $shell->setOutput($output);

        $shell->writeException($exception);
        rewind($stream);
        $this->assertEquals($expected, stream_get_contents($stream));
    }

    public function getRenderedExceptions()
    {
        return array(
            array(new \Exception('{{message}}'), "Exception with message '{{message}}'".PHP_EOL),
        );
    }

    private function getOutput()
    {
        $stream = fopen('php://memory', 'w+');
        $this->streams[] = $stream;

        $output = new StreamOutput($stream, StreamOutput::VERBOSITY_NORMAL, false);

        return $output;
    }
}
