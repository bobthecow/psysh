<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Param;
use PhpParser\Parser;

/**
 * Preserve parameter documentation that PHP-Parser does not attach to the AST.
 *
 * @internal
 */
class ParameterDocCommentPass extends CodeCleanerPass
{
    private Parser $parser;
    private array $tokens = [];

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function beforeTraverse(array $nodes)
    {
        // PHP-Parser 4 does not expose tokens through its parser interface.
        $this->tokens = \method_exists($this->parser, 'getTokens') ? $this->parser->getTokens() : [];

        return null;
    }

    public function enterNode(Node $node)
    {
        if (!$node instanceof Param || !$this->tokens || $node->getStartTokenPos() < 0) {
            return null;
        }

        $start = $node->getStartTokenPos();
        foreach ($node->getComments() as $comment) {
            if ($comment->getStartTokenPos() >= 0) {
                $start = \min($start, $comment->getStartTokenPos());
            }
        }

        if (!empty($node->hooks)) {
            // Promoted parameters capture their docs before the hooks begin.
            $end = ($node->default ?? $node->var)->getEndTokenPos() + 1;
            while (!$this->tokens[$end]->is('{')) {
                ++$end;
            }
            --$end;
        } else {
            // Trailing comments are outside the parameter's AST token range.
            $end = $node->getEndTokenPos();
            while (isset($this->tokens[$end + 1]) && $this->tokens[$end + 1]->is([\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT])) {
                ++$end;
            }
        }

        $ranges = [];
        $this->collectIsolatedRanges($node, $ranges);
        $doc = null;
        for ($pos = $start; $pos <= $end; ++$pos) {
            if (isset($ranges[$pos])) {
                [$pos, $consumesDoc] = $ranges[$pos];
                if ($consumesDoc) {
                    $doc = null;
                }
            } elseif ($this->tokens[$pos]->id === \T_DOC_COMMENT) {
                // Standard already prints comments preceding the parameter.
                $doc = $pos < $node->getStartTokenPos() ? null : $this->tokens[$pos]->text;
            }
        }

        // Keep existing leading comments: a closure default can consume them as
        // its own documentation. Only the final pending doc belongs to the param.
        $node->setAttribute('psysh.parameterDocComment', $doc);

        return null;
    }

    public function afterTraverse(array $nodes)
    {
        $this->tokens = [];

        return null;
    }

    private function collectIsolatedRanges(Node $node, array &$ranges): void
    {
        if ($node instanceof AttributeGroup || $node instanceof Closure || $node instanceof ArrowFunction) {
            $ranges[$node->getStartTokenPos()] = [$node->getEndTokenPos(), !($node instanceof AttributeGroup)];

            return;
        }

        foreach ($node->getSubNodeNames() as $name) {
            foreach (\is_array($node->$name) ? $node->$name : [$node->$name] as $child) {
                if ($child instanceof Node) {
                    $this->collectIsolatedRanges($child, $ranges);
                }
            }
        }
    }
}
