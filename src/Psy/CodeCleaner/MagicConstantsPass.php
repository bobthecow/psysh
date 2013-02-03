<?php

/*
 * This file is part of PsySH
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use Psy\CodeCleaner\CodeCleanerPassInterface;
use PHPParser_Node_Scalar_DirConst as DirConstant;
use PHPParser_Node_Scalar_FileConst as FileConstant;
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Scalar_String as StringNode;
use PHPParser_Node_Name as Name;

/**
 * Swap out __DIR__ and __FILE__ magic constants with our best guess?
 */
class MagicConstantsPass implements CodeCleanerPassInterface
{
    /**
     * Process the syntax tree.
     *
     * CodeCleaner passes may add, remove, or modify statements.
     *
     * @param mixed &$stmts
     */
    public function process(&$stmts)
    {
        if (!is_array($stmts) && !$stmts instanceof \Traversable) {
            throw new \InvalidArgumentException('Unable to validate non-traversable sets.');
        }

        $this->processStatements($stmts);
    }

    protected function processStatements(&$stmts)
    {
        foreach ($stmts as $key => $stmt) {
            if ($stmt instanceof DirConstant) {
                $this->replace($stmts, $key, new FunctionCall(new Name('getcwd'), array(), $stmt->getAttributes()));
            } elseif ($stmt instanceof FileConstant) {
                // TODO: should be an empty string instead?
                $this->replace($stmts, $key, new StringNode('', $stmt->getAttributes()));
            }

            if (is_array($stmt) || (is_object($stmt) && $stmt instanceof \Traversable)) {
                $this->processStatements($stmt);
            }
        }
    }

    protected function replace(&$stmts, $key, $node)
    {
        if (is_object($stmts)) {
            $stmts->$key = $node;
        } elseif (is_array($stmts)) {
            $stmts[$key] = $node;
        }
    }
}
