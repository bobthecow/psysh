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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified as FullyQualifiedName;
use Psy\Exception\BreakException;

class ExitPass extends CodeCleanerPass
{
    /**
     * Converts exit calls to BreakExceptions.
     *
     * @param \PhpParser\Node $node
     *
     * @return int|Node|Node[]|null Replacement node (or special return value)
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Exit_) {
            $args = $node->expr ? [new Arg($node->expr)] : [];

            return new StaticCall(new FullyQualifiedName(BreakException::class), 'exitShell', $args);
        } elseif ($node instanceof FuncCall && $node->name instanceof Name && \in_array(\strtolower($node->name->toString()), ['exit', 'die'], true)) {
            // PHP-Parser represents callable creation and named exit arguments as FuncCall.
            return new StaticCall(new FullyQualifiedName(BreakException::class), 'exitShell', $node->args, $node->getAttributes());
        }

        return null;
    }
}
