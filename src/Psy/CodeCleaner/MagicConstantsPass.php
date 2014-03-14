<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PHPParser_Node as Node;
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Name as Name;
use PHPParser_Node_Scalar_DirConst as DirConstant;
use PHPParser_Node_Scalar_FileConst as FileConstant;
use PHPParser_Node_Scalar_String as StringNode;

/**
 * Swap out __DIR__ and __FILE__ magic constants with our best guess?
 */
class MagicConstantsPass extends CodeCleanerPass
{
    /**
     * Swap out __DIR__ and __FILE__ constants, because the default ones when
     * calling eval() don't make sense.
     *
     * @param Node $node
     *
     * @return null|FunctionCall|StringNode
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof DirConstant) {
            return new FunctionCall(new Name('getcwd'), array(), $node->getAttributes());
        } elseif ($node instanceof FileConstant) {
            return new StringNode('', $node->getAttributes());
        }
    }
}
