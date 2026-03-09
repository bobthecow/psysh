<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

use Psy\Util\TerminalColor;

class TerminalColorTest extends \Psy\Test\TestCase
{
    /**
     * @dataProvider luminanceProvider
     */
    public function testIsLight(array $rgb, bool $expected)
    {
        $this->assertSame($expected, TerminalColor::isLight($rgb));
    }

    /**
     * @return array[]
     */
    public function luminanceProvider(): array
    {
        return [
            'pure black'         => [[0, 0, 0], false],
            'pure white'         => [[255, 255, 255], true],
            'dark gray'          => [[30, 30, 30], false],
            'light gray'         => [[200, 200, 200], true],
            'solarized dark bg'  => [[0, 43, 54], false],
            'solarized light bg' => [[253, 246, 227], true],
            'mid gray'           => [[128, 128, 128], false], // luminance ~128, not > 128
        ];
    }

    public function testLuminanceValues()
    {
        $this->assertEqualsWithDelta(0.0, TerminalColor::luminance([0, 0, 0]), 0.01);
        $this->assertEqualsWithDelta(255.0, TerminalColor::luminance([255, 255, 255]), 0.01);

        // Red has lower perceived luminance than green
        $this->assertLessThan(
            TerminalColor::luminance([0, 255, 0]),
            TerminalColor::luminance([255, 0, 0])
        );
    }

    public function testBlendFullyOpaque()
    {
        $result = TerminalColor::blend([255, 0, 0], [0, 0, 255], 1.0);
        $this->assertSame([255, 0, 0], $result);
    }

    public function testBlendFullyTransparent()
    {
        $result = TerminalColor::blend([255, 0, 0], [0, 0, 255], 0.0);
        $this->assertSame([0, 0, 255], $result);
    }

    public function testBlendHalf()
    {
        $result = TerminalColor::blend([255, 255, 255], [0, 0, 0], 0.5);
        $this->assertSame([128, 128, 128], $result);
    }

    public function testBlendDarkThemeTint()
    {
        // Dark bg (#1a1a1a) + white at 12% → should lighten slightly
        $bg = [26, 26, 26];
        $result = TerminalColor::blend([255, 255, 255], $bg, 0.12);

        $this->assertGreaterThan($bg[0], $result[0]);
        $this->assertGreaterThan($bg[1], $result[1]);
        $this->assertGreaterThan($bg[2], $result[2]);

        // Should still be quite dark
        $this->assertFalse(TerminalColor::isLight($result));
    }

    public function testBlendLightThemeTint()
    {
        // Light bg (#ffffff) + black at 4% → should darken very slightly
        $bg = [255, 255, 255];
        $result = TerminalColor::blend([0, 0, 0], $bg, 0.04);

        $this->assertLessThan($bg[0], $result[0]);
        $this->assertTrue(TerminalColor::isLight($result));
    }

    public function testToHex()
    {
        $this->assertSame('#000000', TerminalColor::toHex([0, 0, 0]));
        $this->assertSame('#ffffff', TerminalColor::toHex([255, 255, 255]));
        $this->assertSame('#1a1a1a', TerminalColor::toHex([26, 26, 26]));
        $this->assertSame('#ff8000', TerminalColor::toHex([255, 128, 0]));
    }

    /**
     * @dataProvider oscResponseProvider
     */
    public function testParseOscResponse(string $response, string $param, ?array $expected)
    {
        $result = TerminalColor::parseOscResponse($response, $param);

        if ($expected === null) {
            $this->assertNull($result);
        } else {
            $this->assertNotNull($result);
            // Allow ±1 for rounding
            $this->assertEqualsWithDelta($expected[0], $result[0], 1);
            $this->assertEqualsWithDelta($expected[1], $result[1], 1);
            $this->assertEqualsWithDelta($expected[2], $result[2], 1);
        }
    }

    /**
     * @return array[]
     */
    public function oscResponseProvider(): array
    {
        return [
            '16-bit black bg' => [
                "\033]11;rgb:0000/0000/0000\033\\",
                '11',
                [0, 0, 0],
            ],
            '16-bit white bg' => [
                "\033]11;rgb:ffff/ffff/ffff\033\\",
                '11',
                [255, 255, 255],
            ],
            '16-bit solarized dark' => [
                "\033]11;rgb:0000/2b2b/3636\033\\",
                '11',
                [0, 43, 54],
            ],
            '8-bit values' => [
                "\033]11;rgb:1a/1a/1a\033\\",
                '11',
                [26, 26, 26],
            ],
            '4-bit values' => [
                "\033]11;rgb:f/0/8\033\\",
                '11',
                [255, 0, 136],
            ],
            '12-bit values' => [
                "\033]11;rgb:aaa/bbb/ccc\033\\",
                '11',
                [170, 187, 204],
            ],
            'foreground query' => [
                "\033]10;rgb:cccc/cccc/cccc\033\\",
                '10',
                [204, 204, 204],
            ],
            'combined bg+fg response' => [
                "\033]11;rgb:0000/0000/0000\033\\\033]10;rgb:ffff/ffff/ffff\033\\",
                '11',
                [0, 0, 0],
            ],
            'combined response - extract fg' => [
                "\033]11;rgb:0000/0000/0000\033\\\033]10;rgb:ffff/ffff/ffff\033\\",
                '10',
                [255, 255, 255],
            ],
            'BEL terminated' => [
                "\033]11;rgb:1a1a/1a1a/1a1a\x07",
                '11',
                [26, 26, 26],
            ],
            'no match' => [
                'garbage data',
                '11',
                null,
            ],
            'wrong param' => [
                "\033]11;rgb:ffff/ffff/ffff\033\\",
                '10',
                null,
            ],
            'reject >16-bit component widths' => [
                "\033]11;rgb:fffff/fffff/fffff\033\\",
                '11',
                null,
            ],
        ];
    }
}
