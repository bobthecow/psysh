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

use Psy\CodeCleaner\CodeCleanerPass;
use Psy\Formatter\SignatureFormatter;
use Psy\Reflection\ReflectionClassConstant;
use Psy\Reflection\ReflectionConstant_;
use Psy\Test\Formatter\Fixtures\BoringTrait;

class SignatureFormatterTest extends \Psy\Test\TestCase
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
        $values = [
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

        if (\version_compare(\PHP_VERSION, '8.0', '>=')) {
            $values[] = [new \ReflectionFunction('implode'), 'function implode(array|string $separator, array $array = null): string'];
            $values[] = [new \ReflectionFunction('array_chunk'), 'function array_chunk(array $array, int $length, bool $preserve_keys = false): array'];
        } else {
            $values[] = [new \ReflectionFunction('implode'), 'function implode($glue, $pieces)'];
            $values[] = [new \ReflectionFunction('array_chunk'), 'function array_chunk($arg, $size, $preserve_keys = unknown)'];
        }

        return $values;
    }

    public function testSignatureFormatterThrowsUnknownReflectorExpeption()
    {
        $this->expectException(\InvalidArgumentException::class);

        $refl = $this->getMockBuilder(\Reflector::class)->getMock();
        SignatureFormatter::format($refl);

        $this->fail();
    }
}
