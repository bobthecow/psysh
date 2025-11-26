<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Output;

use Psy\Output\Theme;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * @group isolation-fail
 */
class ThemeTest extends TestCase
{
    public function testKnownThemes()
    {
        $this->expectNotToPerformAssertions();

        new Theme('modern');
        new Theme('compact');
        new Theme('classic');
    }

    public function testUnknownThemeFallsBackToModern()
    {
        $noticeTriggered = false;
        \set_error_handler(function ($errno, $errstr) use (&$noticeTriggered) {
            if ($errno === \E_USER_NOTICE && \strpos($errstr, 'Unknown theme: nonexistent') !== false) {
                $noticeTriggered = true;

                return true;
            }

            return false;
        });

        try {
            $theme = new Theme('nonexistent');
            // Should fall back to modern theme defaults
            $this->assertFalse($theme->compact());
            $this->assertTrue($noticeTriggered, 'Expected E_USER_NOTICE to be triggered');
        } finally {
            \restore_error_handler();
        }
    }

    public function testArrayConfig()
    {
        $theme = new Theme([
            'compact'      => true,
            'prompt'       => '$ ',
            'bufferPrompt' => '+ ',
            'replayPrompt' => '~ ',
            'returnValue'  => '-> ',
        ]);

        $this->assertTrue($theme->compact());
        $this->assertSame('$ ', $theme->prompt());
        $this->assertSame('+ ', $theme->bufferPrompt());
        $this->assertSame('~ ', $theme->replayPrompt());
        $this->assertSame('-> ', $theme->returnValue());
    }

    public function testInvalidConfigThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid theme config');

        new Theme(123);
    }

    public function testSetCompact()
    {
        $theme = new Theme('modern');
        $this->assertFalse($theme->compact());

        $theme->setCompact(true);
        $this->assertTrue($theme->compact());

        $theme->setCompact(false);
        $this->assertFalse($theme->compact());
    }

    public function testSetPrompt()
    {
        $theme = new Theme('modern');
        $theme->setPrompt('psy> ');
        $this->assertSame('psy> ', $theme->prompt());
    }

    public function testSetBufferPrompt()
    {
        $theme = new Theme('modern');
        $theme->setBufferPrompt('... ');
        $this->assertSame('... ', $theme->bufferPrompt());
    }

    public function testSetReplayPrompt()
    {
        $theme = new Theme('modern');
        $theme->setReplayPrompt('>> ');
        $this->assertSame('>> ', $theme->replayPrompt());
    }

    public function testSetReturnValue()
    {
        $theme = new Theme('modern');
        $theme->setReturnValue('=> ');
        $this->assertSame('=> ', $theme->returnValue());
    }

    public function testGrayFallbackConfig()
    {
        $theme = new Theme([
            'grayFallback' => 'white',
        ]);

        $formatter = new OutputFormatter();
        $theme->applyStyles($formatter, true);

        // When gray fallback is used, gray should be replaced with the fallback color
        $this->assertTrue($formatter->hasStyle('comment'));
    }

    public function testApplyStyles()
    {
        $this->markTestSkipped('Our oldest supported Console versions don\'t like gray');

        $theme = new Theme('modern');
        $formatter = new OutputFormatter();

        $theme->applyStyles($formatter, false);

        // Check that styles are applied
        $this->assertTrue($formatter->hasStyle('info'));
        $this->assertTrue($formatter->hasStyle('error'));
        $this->assertTrue($formatter->hasStyle('warning'));
        $this->assertTrue($formatter->hasStyle('whisper'));
        $this->assertTrue($formatter->hasStyle('aside'));
        $this->assertTrue($formatter->hasStyle('return'));
        $this->assertTrue($formatter->hasStyle('public'));
        $this->assertTrue($formatter->hasStyle('protected'));
        $this->assertTrue($formatter->hasStyle('private'));
        $this->assertTrue($formatter->hasStyle('string'));
        $this->assertTrue($formatter->hasStyle('number'));
        $this->assertTrue($formatter->hasStyle('comment'));
    }

    public function testApplyErrorStyles()
    {
        $this->markTestSkipped('Our oldest supported Console versions don\'t like gray');

        $theme = new Theme('modern');
        $formatter = new OutputFormatter();

        $theme->applyErrorStyles($formatter, false);

        // Check that error-specific styles are applied
        $this->assertTrue($formatter->hasStyle('info'));
        $this->assertTrue($formatter->hasStyle('warning'));
        $this->assertTrue($formatter->hasStyle('error'));
        $this->assertTrue($formatter->hasStyle('whisper'));
        $this->assertTrue($formatter->hasStyle('class'));
    }

    public function testApplyStylesWithGrayFallback()
    {
        $theme = new Theme('modern');
        $formatter = new OutputFormatter();

        $theme->applyStyles($formatter, true);

        // Styles should still be applied even with gray fallback
        $this->assertTrue($formatter->hasStyle('comment'));
        $this->assertTrue($formatter->hasStyle('code_comment'));
    }

    public function testCustomStyles()
    {
        $this->markTestSkipped('Our oldest supported Console versions don\'t like gray');

        $theme = new Theme([
            'styles' => [
                'error' => ['black', 'white', ['bold']],
            ],
        ]);

        $formatter = new OutputFormatter();
        $theme->applyStyles($formatter, false);

        $this->assertTrue($formatter->hasStyle('error'));
    }

    public function testGetInlineStyles()
    {
        $theme = new Theme('modern');
        $inlineStyles = $theme->getInlineStyles();

        $this->assertIsArray($inlineStyles);
        $this->assertArrayHasKey('info', $inlineStyles);
        $this->assertArrayHasKey('error', $inlineStyles);
        $this->assertArrayHasKey('string', $inlineStyles);

        // Check format of inline style strings
        $this->assertStringContainsString('fg=', $inlineStyles['info']);
    }

    public function testGetInlineStylesWithGrayFallback()
    {
        $theme = new Theme('modern');
        $inlineStyles = $theme->getInlineStyles(true);

        $this->assertIsArray($inlineStyles);
        // Gray should be replaced with the fallback color
        $this->assertArrayHasKey('comment', $inlineStyles);
    }
}
