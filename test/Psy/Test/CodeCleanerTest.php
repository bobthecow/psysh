<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\CodeCleaner;

class CodeCleanerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider semicolonCodeProvider
     */
    public function testAutomaticSemicolons(array $lines, $requireSemicolons, $expected)
    {
        $cc = new CodeCleaner();
        $this->assertEquals($expected, $cc->clean($lines, $requireSemicolons));
    }

    public function semicolonCodeProvider()
    {
        $values = array(
            array(array('true'),  false, 'return true;'),
            array(array('true;'), false, 'return true;'),
            array(array('true;'), true,  'return true;'),
            array(array('true'),  true,  false),

            array(array('echo "foo";', 'true'), true,  false),
        );

        if (version_compare(PHP_VERSION, '5.4', '<')) {
            $values[] = array(array('echo "foo";', 'true'), false, "echo 'foo';\nreturn true;");
        } else {
            $values[] = array(array('echo "foo";', 'true'), false, "echo \"foo\";\nreturn true;");
        }

        return $values;
    }

    /**
     * @dataProvider unclosedStatementsProvider
     */
    public function testUnclosedStatements(array $lines, $isUnclosed)
    {
        $cc  = new CodeCleaner();
        $res = $cc->clean($lines);

        if ($isUnclosed) {
            $this->assertFalse($res);
        } else {
            $this->assertNotFalse($res);
        }
    }

    public function unclosedStatementsProvider()
    {
        return array(
            array(array('echo "'),   true),
            array(array('echo \''),  true),
            array(array('if (1) {'), true),

            array(array('echo ""'),   false),
            array(array("echo ''"),   false),
            array(array('if (1) {}'), false),

            array(array('// closed comment'),    false),
            array(array('function foo() { /**'), true),

            array(array('var_dump(1, 2,'), true),
            array(array('var_dump(1, 2,', '3)'), false),
        );
    }

    /**
     * @dataProvider moreUnclosedStatementsProvider
     */
    public function testMoreUnclosedStatements(array $lines)
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM not supported.');
        }

        $cc  = new CodeCleaner();
        $res = $cc->clean($lines);

        $this->assertFalse($res);
    }

    public function moreUnclosedStatementsProvider()
    {
        return array(
            array(array("\$content = <<<EOS\n")),
            array(array("\$content = <<<'EOS'\n")),

            array(array('/* unclosed comment')),
            array(array('/** unclosed comment')),
        );
    }

    /**
     * @dataProvider invalidStatementsProvider
     * @expectedException \Psy\Exception\ParseErrorException
     */
    public function testInvalidStatementsThrowParseErrors($code)
    {
        $cc = new CodeCleaner();
        $cc->clean(array($code));
    }

    public function invalidStatementsProvider()
    {
        return array(
            array('function "what'),
            array("function 'what"),
            array('echo }'),
            array('echo {'),
            array('if (1) }'),
            array('echo """'),
            array("echo '''"),
            array('$foo "bar'),
            array('$foo \'bar'),
            array('var_dump(1,2,)'),
        );
    }
}
