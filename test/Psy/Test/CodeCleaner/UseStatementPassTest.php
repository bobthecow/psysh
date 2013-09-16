<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\UseStatementPass;

class UseStatementPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new UseStatementPass);
    }

    /**
     * @dataProvider useStatements
     */
    public function testProcess($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function useStatements()
    {
        return array(
            array(
                "use StdClass as NotSoStd;\n\$std = new NotSoStd();",
                "\$std = new StdClass();",
            ),
            array(
                "use Foo\\Bar as fb;\n\$baz = new fb\\Baz();",
                "\$baz = new Foo\\Bar\\Baz();",
            ),
        );
    }
}
