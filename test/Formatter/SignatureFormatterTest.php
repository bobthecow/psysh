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

    /**
     * @dataProvider osc8EncodingTestCases
     */
    public function testOsc8UriEncodingInHyperlinks($input, $expectedEncoded)
    {
        // Use reflection to test the private encodeHrefForOsc8 method
        $method = new \ReflectionMethod(SignatureFormatter::class, 'encodeHrefForOsc8');
        $method->setAccessible(true);

        $result = $method->invoke(null, $input);
        $this->assertSame($expectedEncoded, $result);

        // Verify all bytes are in the 32-126 range (printable ASCII)
        for ($i = 0; $i < \strlen($result); $i++) {
            $byte = \ord($result[$i]);
            $this->assertGreaterThanOrEqual(32, $byte, "Byte at position $i is below printable ASCII range");
            $this->assertLessThanOrEqual(126, $byte, "Byte at position $i is above printable ASCII range");
        }
    }

    public function osc8EncodingTestCases()
    {
        return [
            // Already safe ASCII - should pass through unchanged
            ['array_map', 'array_map'],
            ['ArrayObject', 'ArrayObject'],
            ['ArrayObject.offsetGet', 'ArrayObject.offsetGet'],

            // URL-safe characters should pass through unchanged
            ['https://php.net/array_map', 'https://php.net/array_map'],
            ['https://example.com/path?query=value&foo=bar', 'https://example.com/path?query=value&foo=bar'],
            ['http://example.com:8080/path#fragment', 'http://example.com:8080/path#fragment'],
            ['path/to/file.php?a=1&b=2', 'path/to/file.php?a=1&b=2'],
            ['scheme://user:pass@host:123/path?q=v#frag', 'scheme://user:pass@host:123/path?q=v#frag'],
            // Other safe characters: - . _ ~ ! $ & ' ( ) * + , ; = @ : / ?
            ["safe-._~!$&'()*+,;=@:/?", "safe-._~!$&'()*+,;=@:/?"],

            // Characters outside 32-126 range should be encoded
            ['test™', 'test%E2%84%A2'], // Trademark symbol (U+2122)
            ['café', 'caf%C3%A9'], // UTF-8 accented e
            ["test\x01\x1F", 'test%01%1F'], // Control characters
            ["test\x7F", 'test%7F'], // DEL character (127)
            ['test 日本', 'test %E6%97%A5%E6%9C%AC'], // Japanese characters
            ['https://example.com/café', 'https://example.com/caf%C3%A9'], // URL with UTF-8

            // Edge cases: characters at boundaries
            [' ', ' '], // Space (32) - minimum printable ASCII, should pass through
            ['~', '~'], // Tilde (126) - maximum printable ASCII, should pass through
            ["\x1F", '%1F'], // 31 - just below range
            ["\x7F", '%7F'], // 127 - just above range
        ];
    }
}
