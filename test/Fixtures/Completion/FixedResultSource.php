<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures\Completion;

use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;
use Psy\Completion\Source\SourceInterface;

class FixedResultSource implements SourceInterface
{
    /** @var string[] */
    private array $result;

    /**
     * @param string[] $result
     */
    public function __construct(array $result)
    {
        $this->result = $result;
    }

    public function getCompletions(AnalysisResult $analysis): array
    {
        return $this->result;
    }

    public function appliesToKind(int $kinds): bool
    {
        return ($kinds & CompletionKind::VARIABLE) !== 0;
    }
}
