<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\UseStatementPass;

/**
 * @group isolation-fail
 */
class UseStatementPassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new UseStatementPass());
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
        return [
            [
                "use StdClass as NotSoStd;\n\$std = new NotSoStd();",
                "use StdClass as NotSoStd;\n\$std = new NotSoStd();",
            ],
            [
                "namespace Foo;\n\nuse StdClass as S;\n\$std = new S();",
                "namespace Foo;\n\nuse StdClass as S;\n\$std = new S();",
            ],
            [
                "namespace Foo;\n\nuse \\StdClass as S;\n\$std = new S();",
                "namespace Foo;\n\nuse StdClass as S;\n\$std = new S();",
            ],
            [
                "use Foo\\Bar as fb;\n\$baz = new fb\\Baz();",
                "use Foo\\Bar as fb;\n\$baz = new fb\\Baz();",
            ],
            [
                "use Foo\\Bar;\n\$baz = new Bar\\Baz();",
                "use Foo\\Bar;\n\$baz = new Bar\\Baz();",
            ],
            [
                "namespace Foo;\nuse Bar;\n\$baz = new Bar\\Baz();",
                "namespace Foo;\n\nuse Bar;\n\$baz = new Bar\\Baz();",
            ],
            [
                "namespace Foo;\n\nuse \\StdClass as S;\n\$std = new S();\nnamespace Foo;\n\n\$std = new S();",
                "namespace Foo;\n\nuse StdClass as S;\n\$std = new S();\nnamespace Foo;\n\n\$std = new S();",
            ],
            [
                "namespace Foo;\n\nuse \\StdClass as S;\n\$std = new S();\nnamespace Bar;\n\n\$std = new S();",
                "namespace Foo;\n\nuse StdClass as S;\n\$std = new S();\nnamespace Bar;\n\n\$std = new S();",
            ],
            [
                "use Foo\\Bar as fb, Qux as Q;\n\$baz = new fb\\Baz();\n\$qux = new Q();",
                "use Foo\\Bar as fb, Qux as Q;\n\$baz = new fb\\Baz();\n\$qux = new Q();",
            ],
            [
                "use Foo\\Bar;\nuse Bar\\Baz;\n\$baz = new Baz();",
                "use Foo\\Bar;\nuse Bar\\Baz;\n\$baz = new Baz();",
            ],
        ];
    }

    /**
     * @dataProvider groupUseStatements
     */
    public function testGroupUseProcess($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function groupUseStatements()
    {
        return [
            [
                "use Foo\\{Bar, Baz, Qux as Q};\n\$bar = new Bar();\n\$baz = new Baz();\n\$qux = new Q();",
                "use Foo\\{Bar, Baz, Qux as Q};\n\$bar = new Bar();\n\$baz = new Baz();\n\$qux = new Q();",
            ],
            [
                "use X\\{Foo, Bar as B};\n\$foo = new Foo();\n\$baz = new B\\Baz();",
                "use X\\{Foo, Bar as B};\n\$foo = new Foo();\n\$baz = new B\\Baz();",
            ],
            [
                "use X\\{Foo, Bar as B};\n\$foo = new Foo();\n\$bar = new Bar();\n\$baz = new B\\Baz();",
                "use X\\{Foo, Bar as B};\n\$foo = new Foo();\n\$bar = new Bar();\n\$baz = new B\\Baz();",
            ],
        ];
    }

    /**
     * @dataProvider conflictingUseStatements
     */
    public function testConflictingUseStatements($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->expectExceptionMessage('because the name is already in use');
        $this->parseAndTraverse($code);
    }

    public function conflictingUseStatements()
    {
        return [
            ['use StdClass as A; use DateTime as A;'],
            ['use StdClass as Foo; use DateTime as Foo;'],
            ['use Foo\Bar; use Baz\Bar;'],
        ];
    }
}
