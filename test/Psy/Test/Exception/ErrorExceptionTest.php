<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Exception;

use Psy\Exception\ErrorException;
use Psy\Exception\Exception;

class ErrorExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $e = new ErrorException();

        $this->assertTrue($e instanceof Exception);
        $this->assertTrue($e instanceof \ErrorException);
        $this->assertTrue($e instanceof ErrorException);
    }

    public function testMessage()
    {
        $e = new ErrorException('foo');

        $this->assertContains('foo', $e->getMessage());
        $this->assertEquals('foo', $e->getRawMessage());
    }

    /**
     * @dataProvider getLevels
     */
    public function testErrorLevels($level, $type)
    {
        $e = new ErrorException('foo', 0, $level);
        $this->assertContains('PHP ' . $type, $e->getMessage());
    }

    /**
     * @dataProvider getLevels
     */
    public function testThrowException($level, $type)
    {
        try {
            ErrorException::throwException($level, '{whot}', '{file}', '13');
        } catch (ErrorException $e) {
            $this->assertContains('PHP ' . $type, $e->getMessage());
            $this->assertContains('{whot}', $e->getMessage());
            $this->assertContains('in {file}', $e->getMessage());
            $this->assertContains('on line 13', $e->getMessage());
        }
    }

    public function getLevels()
    {
        return array(
            array(E_WARNING,         'Warning'),
            array(E_CORE_WARNING,    'Warning'),
            array(E_COMPILE_WARNING, 'Warning'),
            array(E_USER_WARNING,    'Warning'),
            array(E_STRICT,          'Strict error'),
            array(0,                 'Error'),
        );
    }

    /**
     * @dataProvider getUserLevels
     */
    public function testThrowExceptionAsErrorHandler($level, $type)
    {
        set_error_handler(array('Psy\Exception\ErrorException', 'throwException'));
        try {
            trigger_error('{whot}', $level);
        } catch (ErrorException $e) {
            $this->assertContains('PHP ' . $type, $e->getMessage());
            $this->assertContains('{whot}', $e->getMessage());
        }
        restore_error_handler();
    }

    public function getUserLevels()
    {
        return array(
            array(E_USER_ERROR,      'Error'),
            array(E_USER_WARNING,    'Warning'),
            array(E_USER_NOTICE,     'Notice'),
            array(E_USER_DEPRECATED, 'Deprecated'),
        );
    }

    public function testIgnoreExecutionLoopFilename()
    {
        $e = new ErrorException('{{message}}', 0, 1, '/fake/path/to/Psy/ExecutionLoop/Loop.php');
        $this->assertEmpty($e->getFile());

        $e = new ErrorException('{{message}}', 0, 1, 'c:\fake\path\to\Psy\ExecutionLoop\Loop.php');
        $this->assertEmpty($e->getFile());

        $e = new ErrorException('{{message}}', 0, 1, '/fake/path/to/Psy/File.php');
        $this->assertNotEmpty($e->getFile());
    }
}
