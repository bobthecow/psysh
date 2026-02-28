<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Suggestion;

/**
 * Represents a suggestion result from a suggestion source.
 */
class SuggestionResult
{
    public const SOURCE_HISTORY = 'history';
    public const SOURCE_CALL_SIGNATURE = 'call-signature';
    public const SOURCE_CONTEXT_AWARE = 'context-aware';

    private string $text;
    private string $source;
    private string $fullText;

    /**
     * @param string $text     Just the suggested part (after current buffer)
     * @param string $source   Source type: 'history', 'call-signature', 'context-aware'
     * @param string $fullText Full suggested command/text
     */
    public function __construct(string $text, string $source, string $fullText)
    {
        $this->text = $text;
        $this->source = $source;
        $this->fullText = $fullText;
    }

    /**
     * Get the suggestion text to append.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Get the source that provided this suggestion.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Get the full text that would result from accepting this suggestion.
     */
    public function getFullText(): string
    {
        return $this->fullText;
    }
}
