<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\UseStatementPass;

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
                '$std = new \\StdClass();',
            ],
            [
                "namespace Foo;\n\nuse StdClass as S;\n\$std = new S();",
                "namespace Foo;\n\n\$std = new \\StdClass();",
            ],
            [
                "namespace Foo;\n\nuse \\StdClass as S;\n\$std = new S();",
                "namespace Foo;\n\n\$std = new \\StdClass();",
            ],
            [
                "use Foo\\Bar as fb;\n\$baz = new fb\\Baz();",
                '$baz = new \\Foo\\Bar\\Baz();',
            ],
            [
                "use Foo\\Bar;\n\$baz = new Bar\\Baz();",
                '$baz = new \\Foo\\Bar\\Baz();',
            ],
            [
                "namespace Foo;\nuse Bar;\n\$baz = new Bar\\Baz();",
                "namespace Foo;\n\n\$baz = new \\Bar\\Baz();",
            ],
            [
                "namespace Foo;\n\nuse \\StdClass as S;\n\$std = new S();\nnamespace Foo;\n\n\$std = new S();",
                "namespace Foo;\n\n\$std = new \\StdClass();\nnamespace Foo;\n\n\$std = new \\StdClass();",
            ],
            [
                "namespace Foo;\n\nuse \\StdClass as S;\n\$std = new S();\nnamespace Bar;\n\n\$std = new S();",
                "namespace Foo;\n\n\$std = new \\StdClass();\nnamespace Bar;\n\n\$std = new S();",
            ],
            [
                "use Foo\\Bar as fb, Qux as Q;\n\$baz = new fb\\Baz();\n\$qux = new Q();",
                "\$baz = new \\Foo\\Bar\\Baz();\n\$qux = new \\Qux();",
            ],
            [
                "use Foo\\Bar;\nuse Bar\\Baz;\n\$baz = new Baz();",
                '$baz = new \\Bar\\Baz();',
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
                "\$bar = new \\Foo\\Bar();\n\$baz = new \\Foo\\Baz();\n\$qux = new \\Foo\\Qux();",
            ],
            [
                "use X\\{Foo, Bar as B};\n\$foo = new Foo();\n\$baz = new B\\Baz();",
                "\$foo = new \\X\\Foo();\n\$baz = new \\X\\Bar\\Baz();",
            ],
            [
                "use X\\{Foo, Bar as B};\n\$foo = new Foo();\n\$bar = new Bar();\n\$baz = new B\\Baz();",
                "\$foo = new \\X\\Foo();\n\$bar = new Bar();\n\$baz = new \\X\\Bar\\Baz();",
            ],
        ];
    }
}
