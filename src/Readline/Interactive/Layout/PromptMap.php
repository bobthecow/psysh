<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Layout;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * Prompt strings for first and continuation lines.
 */
class PromptMap
{
    private string $singleLinePrompt = '> ';
    private string $multilinePrompt = '. ';

    /** @var array<string, int> Cached prompt widths keyed by "line:decorated". */
    private array $widthCache = [];

    public function setSingleLinePrompt(string $prompt): void
    {
        $this->singleLinePrompt = $prompt;
        $this->widthCache = [];
    }

    public function setMultilinePrompt(string $prompt): void
    {
        $this->multilinePrompt = $prompt;
        $this->widthCache = [];
    }

    public function getPromptForLine(int $lineNumber): string
    {
        return $lineNumber === 0 ? $this->singleLinePrompt : $this->multilinePrompt;
    }

    public function getPromptWidthForLine(int $lineNumber, ?OutputFormatterInterface $formatter = null): int
    {
        $key = ($lineNumber === 0 ? '0' : '1').($formatter !== null ? ':f' : ':p');
        if (isset($this->widthCache[$key])) {
            return $this->widthCache[$key];
        }

        $prompt = $this->getPromptForLine($lineNumber);
        $width = ($formatter === null)
            ? DisplayString::width($prompt)
            : DisplayString::widthWithoutFormatting($prompt, $formatter);

        $this->widthCache[$key] = $width;

        return $width;
    }
}
