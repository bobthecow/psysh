<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\CodeCleaner\CodeCleanerPass;
use Psy\Formatter\SignatureFormatter;
use Psy\Reflection\ReflectionClassConstant;
use Psy\Reflection\ReflectionConstant_;
use Psy\Test\Formatter\Fixtures\BoringTrait;

class SignatureFormatterTest extends \PHPUnit\Framework\TestCase
{
    const FOO = 'foo value';
    private static $bar = 'bar value';

    private function someFakeMethod(array $one, $two = 'TWO', \Reflector $three = null)
    {
    }

    private function anotherFakeMethod(array $one = [], $two = 2, $three = null)
    {
    }

    /**
     * @dataProvider signatureReflectors
     */
    public function testFormat($reflector, $expected)
    {
        $this->assertSame($expected, \strip_tags(SignatureFormatter::format($reflector)));
    }

    public function signatureReflectors()
    {
        return [
            [
                new \ReflectionFunction('implode'),
                \defined('HHVM_VERSION') ? 'function implode($arg1, $arg2 = null)' : (\version_compare(\PHP_VERSION, '8.0', '>=') ? 'function implode($glue, array $pieces = unknown)' : 'function implode($glue, $pieces)'),
            ],
            [
                ReflectionClassConstant::create($this, 'FOO'),
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
                new \ReflectionClass(CodeCleanerPass::class),
                'abstract class Psy\CodeCleaner\CodeCleanerPass '
                .'extends PhpParser\NodeVisitorAbstract '
                .'implements PhpParser\NodeVisitor',
            ],
            [
                new \ReflectionFunction('array_chunk'),
                \defined('HHVM_VERSION') ? 'function array_chunk($input, $size, $preserve_keys = false)' : (\version_compare(\PHP_VERSION, '8.0', '>=') ? 'function array_chunk(array $arg, $size, $preserve_keys = unknown)' : 'function array_chunk($arg, $size, $preserve_keys = unknown)'),
            ],
            [
                new \ReflectionClass(BoringTrait::class),
                'trait Psy\Test\Formatter\Fixtures\BoringTrait',
            ],
            [
                new \ReflectionMethod(BoringTrait::class, 'boringMethod'),
                'public function boringMethod($one = 1)',
            ],
            [
                new ReflectionConstant_('E_ERROR'),
                'define("E_ERROR", 1)',
            ],
            [
                new ReflectionConstant_('PHP_VERSION'),
                'define("PHP_VERSION", "'.\PHP_VERSION.'")',
            ],
            [
                new ReflectionConstant_('__LINE__'),
                'define("__LINE__", null)', // @todo show this as `unknown` in red or something?
            ],
            [
                new \ReflectionMethod($this, 'anotherFakeMethod'),
                'private function anotherFakeMethod(array $one = [], $two = 2, $three = null)',
            ],
        ];
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSignatureFormatterThrowsUnknownReflectorExpeption()
    {
        $refl = $this->getMockBuilder(\Reflector::class)->getMock();
        SignatureFormatter::format($refl);
    }
}
