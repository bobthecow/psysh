<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Completion\Refiner;

use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;

/**
 * Recovers useful completion lanes when valid PHP syntax is not yet available.
 *
 * This refiner keeps completion responsive while the user is still in the
 * middle of typing an expression the parser cannot fully classify yet.
 */
class PartialInputRefiner implements AnalysisRefinerInterface
{
    /**
     * {@inheritdoc}
     */
    public function refine(AnalysisResult $analysis): AnalysisResult
    {
        if (!$analysis->parseSucceeded) {
            $partial = $this->analyzePartialInput($analysis->input);

            return $analysis->withContext($partial->kinds, $partial->prefix, $partial->leftSide, $partial->leftSideNode);
        }

        if ($analysis->kinds !== CompletionKind::UNKNOWN) {
            return $analysis;
        }

        $partial = $this->analyzePartialInput($analysis->input);

        return $this->shouldPreferPartialAnalysis($partial)
            ? $analysis->withContext($partial->kinds, $partial->prefix, $partial->leftSide, $partial->leftSideNode)
            : $analysis;
    }

    private function analyzePartialInput(string $input): AnalysisResult
    {
        $trimmed = \rtrim($input);
        $hasTrailingSpace = $input !== $trimmed;

        if (\preg_match('/\bnew\s+([\w\\\\]*)$/i', $input, $matches)) {
            return new AnalysisResult(CompletionKind::CLASS_NAME, $matches[1]);
        }

        if (\preg_match('/^.*[;\{\}]\s*(\w+)$/', $trimmed, $matches)) {
            if (!\preg_match('/(?:->|\?->)\w*$/', $trimmed) && !\preg_match('/::\w*$/', $trimmed)) {
                if ($hasTrailingSpace) {
                    return new AnalysisResult(CompletionKind::UNKNOWN, '');
                }

                return new AnalysisResult(CompletionKind::KEYWORD | CompletionKind::SYMBOL, $matches[1]);
            }
        }

        if (\preg_match('/(\$\w+)(?:->|\?->)([\w]*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::OBJECT_MEMBER, $matches[2], $matches[1]);
        }

        if (\preg_match('/([\w\\\\]*\\\\)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::SYMBOL | CompletionKind::NAMESPACE, $matches[1]);
        }

        if (\preg_match('/([\w\\\\]+)::\$(\w*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::STATIC_MEMBER, $matches[2], $matches[1]);
        }

        if (\preg_match('/([\w\\\\]+)::([\w]*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::STATIC_MEMBER, $matches[2], $matches[1]);
        }

        if (\preg_match('/\$(\w*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::VARIABLE, $matches[1]);
        }

        if (\preg_match('/([\w\\\\]+)$/', $trimmed, $matches)) {
            if ($hasTrailingSpace) {
                return new AnalysisResult(CompletionKind::UNKNOWN, '');
            }

            return new AnalysisResult(CompletionKind::SYMBOL, $matches[1]);
        }

        return new AnalysisResult(CompletionKind::UNKNOWN, '');
    }

    private function shouldPreferPartialAnalysis(AnalysisResult $partialAnalysis): bool
    {
        return \in_array($partialAnalysis->kinds, [
            CompletionKind::VARIABLE,
            CompletionKind::OBJECT_MEMBER,
            CompletionKind::STATIC_MEMBER,
            CompletionKind::CLASS_NAME,
            CompletionKind::SYMBOL | CompletionKind::NAMESPACE,
        ], true);
    }
}
