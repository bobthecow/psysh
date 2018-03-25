<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\SignatureFormatter;
use Psy\Reflection\ReflectionConstant;

class SignatureFormatterTest extends \PHPUnit\Framework\TestCase
{
    const FOO = 'foo value';
    private static $bar = 'bar value';

    private function someFakeMethod(array $one, $two = 'TWO', \Reflector $three = null)
    {
    }

    /**
     * @dataProvider signatureReflectors
     */
    public function testFormat($reflector, $expected)
    {
        $this->assertSame($expected, strip_tags(SignatureFormatter::format($reflector)));
    }

    public function signatureReflectors()
    {
        return [
            [
                new \ReflectionFunction('implode'),
                defined('HHVM_VERSION') ? 'function implode($arg1, $arg2 = null)' : 'function implode($glue, $pieces)',
            ],
            [
                new ReflectionConstant($this, 'FOO'),
                'const FOO = "foo value"',
            ],
            [
                new \ReflectionMethod($this, 'someFakeMethod'),
                'private function someFakeMethod(array $one, $two = \'TWO\', Reflector $three = null)',
            ],
            [
                new \ReflectionProperty($this, 'bar'),
                'private static $bar',
            ],
            [
                new \ReflectionClass('Psy\CodeCleaner\CodeCleanerPass'),
                'abstract class Psy\CodeCleaner\CodeCleanerPass '
                . 'extends PhpParser\NodeVisitorAbstract '
                . 'implements PhpParser\NodeVisitor',
            ],
        ];
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSignatureFormatterThrowsUnknownReflectorExpeption()
    {
        $refl = $this->getMockBuilder('Reflector')->getMock();
        SignatureFormatter::format($refl);
    }
}
