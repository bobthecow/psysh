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

use Psy\Formatter\LinkFormatter;

class LinkFormatterTest extends \Psy\Test\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear styles before each test
        LinkFormatter::setStyles([]);
    }

    public function testStyleWithHrefWithoutHyperlink()
    {
        $result = LinkFormatter::styleWithHref('info', 'array_map', null);
        $this->assertSame('<info>array_map</info>', $result);
    }

    /**
     * @group php-parser-v4-fail
     */
    public function testStyleWithHrefEscapesText()
    {
        $result = LinkFormatter::styleWithHref('info', '<test>', null);
        $this->assertSame('<info>\<test\></info>', $result);
    }

    public function testStyleWithHrefIncludesInlineStyles()
    {
        // Set up inline styles
        LinkFormatter::setStyles([
            'function' => 'fg=blue;options=bold',
            'class'    => 'fg=green',
            'info'     => 'fg=cyan',
        ]);

        // Mock hyperlink support by checking if the method exists
        if (!LinkFormatter::supportsLinks()) {
            $this->markTestSkipped('Hyperlinks not supported in this Symfony Console version');
        }

        $result = LinkFormatter::styleWithHref('function', 'array_map', 'https://php.net/array-map');

        // Should include inline style with href
        $this->assertStringContainsString('fg=blue;options=bold;href=https://php.net/array-map', $result);
        $this->assertStringContainsString('array_map', $result);
    }

    public function testStyleWithHrefWithoutInlineStyle()
    {
        // Don't set any inline styles
        LinkFormatter::setStyles([]);

        if (!LinkFormatter::supportsLinks()) {
            $this->markTestSkipped('Hyperlinks not supported in this Symfony Console version');
        }

        $result = LinkFormatter::styleWithHref('info', 'test', 'https://example.com');

        // Should have href without inline style prefix
        $this->assertStringContainsString('href=https://example.com', $result);
        // Should NOT start with a semicolon
        $this->assertStringNotContainsString(';href=', $result);
        $this->assertStringContainsString('test', $result);
    }

    public function testGetPhpNetUrl()
    {
        $this->assertSame('https://php.net/array-map', LinkFormatter::getPhpNetUrl('array_map'));
        $this->assertSame('https://php.net/array-map', LinkFormatter::getPhpNetUrl('Array_Map'));
        $this->assertSame('https://php.net/pdo.--construct', LinkFormatter::getPhpNetUrl('PDO::__construct'));
    }

    /**
     * @dataProvider osc8EncodingTestCases
     */
    public function testEncodeHrefForOsc8($input, $expectedEncoded)
    {
        $result = LinkFormatter::encodeHrefForOsc8($input);
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

    public function testSupportsLinks()
    {
        $supportsLinks = LinkFormatter::supportsLinks();
        $this->assertIsBool($supportsLinks);

        // The result depends on Symfony Console version
        // Just verify it returns a boolean without error
    }

    public function testSetStylesPersistsAcrossCalls()
    {
        LinkFormatter::setStyles([
            'function' => 'fg=blue',
            'class'    => 'fg=green',
        ]);

        if (!LinkFormatter::supportsLinks()) {
            $this->markTestSkipped('Hyperlinks not supported in this Symfony Console version');
        }

        // First call
        $result1 = LinkFormatter::styleWithHref('function', 'test1', 'https://example.com/1');
        $this->assertStringContainsString('fg=blue', $result1);

        // Second call - should still have the styles
        $result2 = LinkFormatter::styleWithHref('class', 'test2', 'https://example.com/2');
        $this->assertStringContainsString('fg=green', $result2);
    }
}
