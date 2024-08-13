<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Exception;

use Psy\Exception\ErrorException;
use Psy\Exception\Exception;

class ErrorExceptionTest extends \Psy\Test\TestCase
{
    public function testInstance()
    {
        $e = new ErrorException();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(\ErrorException::class, $e);
        $this->assertInstanceOf(ErrorException::class, $e);
    }

    public function testMessage()
    {
        $e = new ErrorException('foo');

        $this->assertStringContainsString('foo', $e->getMessage());
        $this->assertSame('foo', $e->getRawMessage());
    }

    /**
     * @dataProvider getLevels
     */
    public function testErrorLevels($level, $type)
    {
        $e = new ErrorException('foo', 0, $level);
        $this->assertStringContainsString('PHP '.$type, $e->getMessage());
    }

    /**
     * @dataProvider getLevels
     */
    public function testThrowException($level, $type)
    {
        try {
            ErrorException::throwException($level, '{whot}', '{file}', '13');
        } catch (ErrorException $e) {
            $this->assertStringContainsString('PHP '.$type, $e->getMessage());
            $this->assertStringContainsString('{whot}', $e->getMessage());
            $this->assertStringContainsString('in {file}', $e->getMessage());
            $this->assertStringContainsString('on line 13', $e->getMessage());
        }
    }

    public function getLevels()
    {
        return [
            [\E_WARNING,           'Warning'],
            [\E_CORE_WARNING,      'Warning'],
            [\E_COMPILE_WARNING,   'Warning'],
            [\E_USER_WARNING,      'Warning'],
            [\E_STRICT,            'Strict error'],
            [\E_DEPRECATED,        'Deprecated'],
            [\E_USER_DEPRECATED,   'Deprecated'],
            [\E_RECOVERABLE_ERROR, 'Recoverable fatal error'],
            [0,                    'Error'],
        ];
    }

    /**
     * @dataProvider getUserLevels
     */
    public function testThrowExceptionAsErrorHandler($level, $type)
    {
        if (\version_compare(\PHP_VERSION, '8.4', '>=') && $level === \E_USER_ERROR) {
            $this->markTestSkipped('Passing E_USER_ERROR to trigger_error() is deprecated since 8.4');
        }

        \set_error_handler([ErrorException::class, 'throwException']);
        try {
            \trigger_error('{whot}', $level);
        } catch (ErrorException $e) {
            $this->assertStringContainsString('PHP '.$type, $e->getMessage());
            $this->assertStringContainsString('{whot}', $e->getMessage());
        }
        \restore_error_handler();
    }

    public function getUserLevels()
    {
        return [
            [\E_USER_ERROR,      'Error'],
            [\E_USER_WARNING,    'Warning'],
            [\E_USER_NOTICE,     'Notice'],
            [\E_USER_DEPRECATED, 'Deprecated'],
        ];
    }

    public function testIgnoreExecutionLoopFilename()
    {
        $e = new ErrorException('{{message}}', 0, 1, '/fake/path/to/Psy/ExecutionLoop.php');
        $this->assertEmpty($e->getFile());

        $e = new ErrorException('{{message}}', 0, 1, 'c:\fake\path\to\Psy\ExecutionLoop.php');
        $this->assertEmpty($e->getFile());

        $e = new ErrorException('{{message}}', 0, 1, '/fake/path/to/Psy/File.php');
        $this->assertNotEmpty($e->getFile());
    }
}
