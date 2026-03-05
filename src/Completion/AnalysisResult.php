<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Completion;

use PhpParser\Node;

/**
 * Completion analysis result.
 *
 * Contains information about the syntactic context where completion is valid,
 * what's being completed, and any resolved type information.
 */
class AnalysisResult
{
    public int $kinds;
    public string $prefix;
    public ?string $leftSide;
    public ?Node $leftSideNode;
    /** @var string[] Fully-qualified class names (supports union types) */
    public array $leftSideTypes;
    public $leftSideValue;
    public string $input;
    /** @var array Tokenized input */
    public array $tokens;

    /**
     * @param string|string[]|null $leftSideTypes
     */
    public function __construct(
        int $kinds,
        string $prefix = '',
        ?string $leftSide = null,
        $leftSideTypes = [],
        $leftSideValue = null,
        array $tokens = [],
        string $input = '',
        ?Node $leftSideNode = null
    ) {
        $this->kinds = $kinds;
        $this->prefix = $prefix;
        $this->leftSide = $leftSide;
        $this->leftSideNode = $leftSideNode;
        $this->leftSideTypes = (array) $leftSideTypes;
        $this->leftSideValue = $leftSideValue;
        $this->tokens = $tokens;
        $this->input = $input;
    }
}
