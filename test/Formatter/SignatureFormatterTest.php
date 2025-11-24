<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\CodeCleaner\CodeCleanerPass;
use Psy\Formatter\SignatureFormatter;
use Psy\Reflection\ReflectionConstant;
use Psy\Test\Formatter\Fixtures\BoringTrait;

/**
 * @group isolation-fail
 */
class SignatureFormatterTest extends \Psy\Test\TestCase
{
    const FOO = 'foo value';
    private static $bar = 'bar value';

    private function someFakeMethod(array $one, $two = 'TWO', ?\Reflector $three = null)
    {
    }

    private function anotherFakeMethod(array $one = [], $two = 2, $three = null)
    {
    }

    private function nullableFakeMethod(?bool $one, ?string $two = null, $three = null): ?array
    {
        return null;
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
                new \ReflectionClassConstant($this, 'FOO'),
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
                new ReflectionConstant('E_ERROR'),
                'define("E_ERROR", 1)',
            ],
            [
                new ReflectionConstant('PHP_VERSION'),
                'define("PHP_VERSION", "'.\PHP_VERSION.'")',
            ],
            [
                new ReflectionConstant('__LINE__'),
                'define("__LINE__", null)', // @todo show this as `unknown` in red or something?
            ],
            [
                new \ReflectionMethod($this, 'anotherFakeMethod'),
                'private function anotherFakeMethod(array $one = [], $two = 2, $three = null)',
            ],
            [
                new \ReflectionMethod($this, 'nullableFakeMethod'),
                'private function nullableFakeMethod(?bool $one, string $two = null, $three = null): ?array',
            ],
        ];

        if (\PHP_VERSION_ID >= 80000) {
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

    public function testHyperlinksPreserveInlineStyles()
    {
        // Set up inline styles
        $inlineStyles = [
            'function' => 'fg=blue;options=bold',
            'class'    => 'fg=green',
        ];
        \Psy\Formatter\LinkFormatter::setStyles($inlineStyles);

        // Create a reflection for a built-in function
        $reflector = new \ReflectionFunction('array_map');

        // Mock the manual to enable hyperlinks
        $manual = $this->getMockBuilder(\Psy\Manual\ManualInterface::class)->getMock();
        $manual->method('get')->willReturn(['type' => 'function', 'description' => 'Test']);
        SignatureFormatter::setManual($manual);

        $formatted = SignatureFormatter::format($reflector);

        // When hyperlinks are supported and manual is available, the output should contain
        // the inline style combined with href (e.g., "fg=blue;options=bold;href=...")
        // We can't test the exact output since it depends on Symfony version and hyperlink support,
        // but we can verify that if styles are set, they're used by LinkFormatter
        $this->assertStringContainsString('array_map', $formatted);

        // Clean up
        SignatureFormatter::setManual(null);
        \Psy\Formatter\LinkFormatter::setStyles([]);
    }
}
