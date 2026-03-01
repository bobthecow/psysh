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
use Psy\Readline\Interactive\Helper\TokenHelper;

/**
 * Determines whether a buffer is ready to execute as a statement.
 */
class StatementCompletenessPolicy
{
    private ParseSnapshotCache $parseSnapshotCache;
    private bool $requireSemicolons;

    public function __construct(ParseSnapshotCache $parseSnapshotCache, bool $requireSemicolons = false)
    {
        $this->parseSnapshotCache = $parseSnapshotCache;
        $this->requireSemicolons = $requireSemicolons;
    }

    public function isCompleteStatement(string $line): bool
    {
        if ($line === '') {
            return true;
        }

        $snapshot = $this->parseSnapshotCache->getSnapshot($line);
        $tokens = $snapshot->getTokens();
        $lastError = $snapshot->getLastError();

        // Control structures like `if (...)` are incomplete in REPL context.
        if ($this->hasControlStructureWithoutBody($line, $lastError)) {
            return false;
        }

        if ($lastError === null) {
            if (!$this->hasBalancedBracketsFromTokens($tokens)) {
                return false;
            }

            if (TokenHelper::hasTrailingOperator($tokens)) {
                return false;
            }

            return true;
        }

        if (!$this->requireSemicolons && $this->isEOFError($lastError)) {
            if ($this->parseSnapshotCache->canBeFixedWithSemicolon($line)) {
                return true;
            }
        }

        if ($this->isUnterminatedComment($lastError) || $this->isUnclosedString($lastError)) {
            return false;
        }

        if (!$this->isEOFError($lastError)) {
            // Real syntax errors should execute immediately so the user sees them.
            return true;
        }

        return false;
    }

    /**
     * Check whether the buffer has a non-EOF parse error that isn't recoverable
     * by adding more input (e.g. extra closing brackets, invalid syntax).
     */
    public function hasUnrecoverableSyntaxError(string $line): bool
    {
        if ($line === '') {
            return false;
        }

        $snapshot = $this->parseSnapshotCache->getSnapshot($line);
        $lastError = $snapshot->getLastError();

        if ($lastError === null) {
            return false;
        }

        if ($this->isEOFError($lastError)) {
            return false;
        }

        if ($this->isUnterminatedComment($lastError) || $this->isUnclosedString($lastError)) {
            return false;
        }

        // Control structures without body aren't syntax errors, they're incomplete.
        if ($this->hasControlStructureWithoutBody($line, $lastError)) {
            return false;
        }

        return true;
    }

    public function hasBalancedBrackets(string $line): bool
    {
        $tokens = $this->parseSnapshotCache->getSnapshot($line)->getTokens();

        return $this->hasBalancedBracketsFromTokens($tokens);
    }

    private function isEOFError(Error $error): bool
    {
        $msg = $error->getRawMessage();

        return ($msg === 'Unexpected token EOF') || (\strpos($msg, 'Syntax error, unexpected EOF') !== false);
    }

    private function isUnterminatedComment(Error $error): bool
    {
        return $error->getRawMessage() === 'Unterminated comment';
    }

    private function isUnclosedString(Error $error): bool
    {
        $msg = $error->getRawMessage();

        return $msg === 'Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE' ||
               \strpos($msg, 'Unterminated string') !== false;
    }

    private function hasBalancedBracketsFromTokens(array $tokens): bool
    {
        $stack = [];
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];

        foreach ($tokens as $token) {
            if (\is_array($token)) {
                continue;
            }

            if (isset($pairs[$token])) {
                $stack[] = $token;
            } elseif (\in_array($token, $pairs, true)) {
                if (empty($stack)) {
                    return false;
                }

                $last = \array_pop($stack);
                if ($pairs[$last] !== $token) {
                    return false;
                }
            }
        }

        return empty($stack);
    }

    private function hasControlStructureWithoutBody(string $line, ?Error $lastError): bool
    {
        $trimmed = \rtrim($line);

        if (\preg_match('/\b(if|while|for|foreach|elseif)\s*\(.*\)\s*$/', $trimmed)) {
            $lastParen = \strrpos($trimmed, ')');
            if ($lastParen !== false) {
                $afterParen = \trim(\substr($trimmed, $lastParen + 1));
                if ($afterParen === '') {
                    if ($lastError !== null && !$this->isEOFError($lastError)) {
                        return false;
                    }

                    return true;
                }
            }
        }

        $isElseAfterBrace = \preg_match('/\}\s*else\s*$/', $trimmed);
        $isBareElse = \preg_match('/^\s*else\s*$/', $trimmed);

        if ($isElseAfterBrace || $isBareElse) {
            if ($isBareElse && $lastError !== null && !$this->isEOFError($lastError)) {
                return false;
            }

            return true;
        }

        return false;
    }
}
