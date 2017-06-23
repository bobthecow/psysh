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
                array('require $foo', "require $resolve(\$foo, 1);"),
                array('$bar = require $baz', "\$bar = (require $resolve(\$baz, 1));"),
            );
        }

        return array(
            // The basics
            array('require "a"', "require $resolve(\"a\", 1);"),
            array('require "b.php"', "require $resolve(\"b.php\", 1);"),
            array('require_once "c"', "require_once $resolve(\"c\", 1);"),
            array('require_once "d.php"', "require_once $resolve(\"d.php\", 1);"),

            // Ensure that line numbers work correctly
            array("null;\nrequire \"e.php\"", "null;\nrequire $resolve(\"e.php\", 2);"),
            array("null;\nrequire_once \"f.php\"", "null;\nrequire_once $resolve(\"f.php\", 2);"),

            // Things with expressions
            array('require $foo', "require $resolve(\$foo, 1);"),
            array('require_once $foo', "require_once $resolve(\$foo, 1);"),
            array('require ($bar = "g.php")', "require $resolve(\$bar = \"g.php\", 1);"),
            array('require_once ($bar = "h.php")', "require_once $resolve(\$bar = \"h.php\", 1);"),
            array('$bar = require ($baz = "i.php")', "\$bar = (require $resolve(\$baz = \"i.php\", 1));"),
            array('$bar = require_once ($baz = "j.php")', "\$bar = (require_once $resolve(\$baz = \"j.php\", 1));"),
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
