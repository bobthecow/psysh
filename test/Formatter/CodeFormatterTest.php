<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\CodeFormatter;
use Psy\Test\Formatter\Fixtures\SomeClass;

class CodeFormatterTest extends \Psy\Test\TestCase
{
    /**
     * @dataProvider reflectors
     */
    public function testFormat($reflector, $expected)
    {
        $formatted = CodeFormatter::format($reflector);
        $formattedWithoutColors = self::stripTags($formatted);

        $this->assertSame($expected, self::trimLines($formattedWithoutColors));
        $this->assertNotEquals($expected, self::trimLines($formatted));
    }

    public function reflectors()
    {
        $expectClass = <<<'EOS'
14: class SomeClass
15: {
16:     const SOME_CONST = 'some const';
17:     private $someProp = 'some prop';
18:
19:     public function someMethod($someParam)
20:     {
21:         return 'some method';
22:     }
23:
24:     public static function someClosure()
25:     {
26:         return function () {
27:             return 'some closure';
28:         };
29:     }
30: }
EOS;

        $expectMethod = <<<'EOS'
19:     public function someMethod($someParam)
20:     {
21:         return 'some method';
22:     }
EOS;

        $expectClosure = <<<'EOS'
26:         return function () {
27:             return 'some closure';
28:         };
EOS;

        return [
            [new \ReflectionClass(SomeClass::class), $expectClass],
            [new \ReflectionObject(new SomeClass()), $expectClass],
            [new \ReflectionMethod(SomeClass::class, 'someMethod'), $expectMethod],
            [new \ReflectionFunction(SomeClass::someClosure()), $expectClosure],
        ];
    }

    /**
     * @dataProvider invalidReflectors
     */
    public function testCodeFormatterThrowsExceptionForReflectorsItDoesntUnderstand($reflector)
    {
        $this->expectException(\Psy\Exception\RuntimeException::class);
        CodeFormatter::format($reflector);

        $this->fail();
    }

    public function invalidReflectors()
    {
        return [
            [new \ReflectionExtension('json')],
            [new \ReflectionParameter([SomeClass::class, 'someMethod'], 'someParam')],
            [new \ReflectionProperty(SomeClass::class, 'someProp')],
            [new \ReflectionClassConstant(SomeClass::class, 'SOME_CONST')],
        ];
    }

    /**
     * @dataProvider filenames
     */
    public function testCodeFormatterThrowsExceptionForMissingFile($filename)
    {
        $this->expectException(\Psy\Exception\RuntimeException::class);

        $reflector = $this->getMockBuilder(\ReflectionClass::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflector
            ->expects($this->once())
            ->method('getFileName')
            ->willReturn($filename);

        CodeFormatter::format($reflector);

        $this->fail();
    }

    public function filenames()
    {
        return [[false], ['not a file']];
    }

    /**
     * @dataProvider validCode
     */
    public function testFormatCode($code, $startLine, $endLine, $markLine, $expected)
    {
        $formatted = CodeFormatter::formatCode($code, $startLine, $endLine, $markLine);
        $formattedWithoutColors = self::stripTags($formatted);

        $this->assertSame($expected, self::trimLines($formattedWithoutColors));
        $this->assertNotEquals($expected, self::trimLines($formatted));
    }

    public function validCode()
    {
        $someCode = <<<'EOS'
<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter\Fixtures;

class SomeClass
{
    const SOME_CONST = 'some const';
    private $someProp = 'some prop';

    public function someMethod($someParam)
    {
        return 'some method';
    }

    public static function someClosure()
    {
        return function () {
            return 'some closure';
        };
    }
}
EOS;

        $someCodeExpected = <<<'EOS'
 1: <?php
 2:
 3: /*
 4:  * This file is part of Psy Shell.
 5:  *
 6:  * (c) 2012-2023 Justin Hileman
 7:  *
 8:  * For the full copyright and license information, please view the LICENSE
 9:  * file that was distributed with this source code.
10:  */
11:
12: namespace Psy\Test\Formatter\Fixtures;
13:
14: class SomeClass
15: {
16:     const SOME_CONST = 'some const';
17:     private $someProp = 'some prop';
18:
19:     public function someMethod($someParam)
20:     {
21:         return 'some method';
22:     }
23:
24:     public static function someClosure()
25:     {
26:         return function () {
27:             return 'some closure';
28:         };
29:     }
30: }
EOS;

        $someCodeSnippet = <<<'EOS'
19:     public function someMethod($someParam)
20:     {
21:         return 'some method';
22:     }
EOS;

        $someCodeSnippetWithMarker = <<<'EOS'
    19:     public function someMethod($someParam)
  > 20:     {
    21:         return 'some method';
    22:     }
EOS;

        return [
            [$someCode, 1, null, null, $someCodeExpected],
            [$someCode, 19, 22, null, $someCodeSnippet],
            [$someCode, 19, 22, 20, $someCodeSnippetWithMarker],
        ];
    }

    /**
     * Test some smaller ones with spans... we don't want the test to be tooo flaky so we don't
     * explicitly test the exact formatting above. Just to be safe, let's add a couple of tests
     * that *do* expect specific formatting.
     *
     * @dataProvider smallCodeLines
     */
    public function testFormatSmallCodeLines($code, $startLine, $endLine, $markLine, $expected)
    {
        $formatted = CodeFormatter::formatCode($code, $startLine, $endLine, $markLine);
        $this->assertSame($expected, self::trimLines($formatted));
    }

    public function smallCodeLines()
    {
        return [
            ['<?php $foo = 42;', 1, null, null, '<aside>1</aside>: \\<?php $foo <keyword>= </keyword><number>42</number><keyword>;</keyword>'],
            ['<?php echo "yay $foo!";', 1, null, null, '<aside>1</aside>: \\<?php <keyword>echo </keyword><string>"yay </string>$foo<string>!"</string><keyword>;</keyword>'],

            // Start and end lines
            ["<?php echo 'wat';\n\$foo = 42;", 1, 1, null, '<aside>1</aside>: \\<?php <keyword>echo </keyword><string>\'wat\'</string><keyword>;</keyword>'],
            ["<?php echo 'wat';\n\$foo = 42;", 2, 2, null, '<aside>2</aside>: $foo <keyword>= </keyword><number>42</number><keyword>;</keyword>'],
            ["<?php echo 'wat';\n\$foo = 42;", 2, null, null, '<aside>2</aside>: $foo <keyword>= </keyword><number>42</number><keyword>;</keyword>'],

            // With a line marker
            ["<?php echo 'wat';\n\$foo = 42;", 2, null, 2, '  <urgent>></urgent> <aside>2</aside>: $foo <keyword>= </keyword><number>42</number><keyword>;</keyword>'],

            // Line marker before or after our line range
            ["<?php echo 'wat';\n\$foo = 42;", 2, null, 1, '<aside>2</aside>: $foo <keyword>= </keyword><number>42</number><keyword>;</keyword>'],
            ["<?php echo 'wat';\n\$foo = 42;", 1, 1, 3, '<aside>1</aside>: \<?php <keyword>echo </keyword><string>\'wat\'</string><keyword>;</keyword>'],
        ];
    }

    /**
     * Remove tags from formatted output. This is kind of ugly o_O.
     */
    private static function stripTags($code)
    {
        $tagRegex = '[a-z][^<>]*+';
        $output = \preg_replace("#<(($tagRegex) | /($tagRegex)?)>#ix", '', $code);

        return \str_replace('\\<', '<', $output);
    }

    private static function trimLines($code)
    {
        return \rtrim(\implode("\n", \array_map('rtrim', \explode("\n", $code))));
    }
}
