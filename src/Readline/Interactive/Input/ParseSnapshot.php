<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Input;

use PhpParser\Error;

/**
 * Cached parse data for a buffer snapshot.
 */
class ParseSnapshot
{
    private array $tokens;
    private array $tokenPositions;
    private ?array $ast;
    private ?Error $lastError;

    /**
     * @param array      $tokens
     * @param array      $tokenPositions
     * @param array|null $ast
     * @param Error|null $lastError
     */
    public function __construct(array $tokens, array $tokenPositions, ?array $ast, ?Error $lastError)
    {
        $this->tokens = $tokens;
        $this->tokenPositions = $tokenPositions;
        $this->ast = $ast;
        $this->lastError = $lastError;
    }

    /**
     * Get the token_get_all() tokens for the buffer.
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Get start/end code-point positions for each token.
     */
    public function getTokenPositions(): array
    {
        return $this->tokenPositions;
    }

    /**
     * Get the parsed AST, or null if parsing failed.
     */
    public function getAst(): ?array
    {
        return $this->ast;
    }

    /**
     * Get the last parse error, if any.
     */
    public function getLastError(): ?Error
    {
        return $this->lastError;
    }
}
