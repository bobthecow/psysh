<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\RequirePass;

class RequirePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new RequirePass());
    }

    /**
     * @dataProvider exitStatements
     */
    public function testExitStatement($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function exitStatements()
    {
        $resolve = '\\Psy\\CodeCleaner\\RequirePass::resolve';

        if (version_compare(PHP_VERSION, '5.4', '<')) {
            return array(
                array('require $foo', "$resolve(\$foo, 1);"),
                array('$bar = require $baz', "\$bar = $resolve(\$baz, 1);"),
            );
        }

        return array(
            // The basics
            array('require "a"', "$resolve(\"a\", 1);"),
            array('require "b.php"', "$resolve(\"b.php\", 1);"),
            array('require_once "c"', "$resolve(\"c\", 1);"),
            array('require_once "d.php"', "$resolve(\"d.php\", 1);"),

            // Ensure that line numbers work correctly
            array("null;\nrequire \"e.php\"", "null;\n$resolve(\"e.php\", 2);"),
            array("null;\nrequire_once \"f.php\"", "null;\n$resolve(\"f.php\", 2);"),

            // Things with expressions
            array('require $foo', "$resolve(\$foo, 1);"),
            array('require_once $foo', "$resolve(\$foo, 1);"),
            array('require ($bar = "g.php")', "$resolve(\$bar = \"g.php\", 1);"),
            array('require_once ($bar = "h.php")', "$resolve(\$bar = \"h.php\", 1);"),
            array('$bar = require ($baz = "i.php")', "\$bar = $resolve(\$baz = \"i.php\", 1);"),
            array('$bar = require_once ($baz = "j.php")', "\$bar = $resolve(\$baz = \"j.php\", 1);"),
        );
    }

    /**
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage Failed opening required 'not a file name' in eval()'d code on line 2
     */
    public function testResolve()
    {
        RequirePass::resolve('not a file name', 2);
    }

    /**
     * @dataProvider emptyWarnings
     *
     * @expectedException \Psy\Exception\ErrorException
     * @expectedExceptionMessage Filename cannot be empty on line 1
     */
    public function testResolveEmptyWarnings($file)
    {
        if (!E_WARNING & error_reporting()) {
            $this->markTestSkipped();
        }

        RequirePass::resolve($file, 1);
    }

    public function emptyWarnings()
    {
        return array(
            array(null),
            array(false),
            array(''),
        );
    }
}
