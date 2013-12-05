<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PHPParser_Node as Node;
use PHPParser_Node_Expr_Instanceof as InstanceOfNode;
use PHPParser_Node_Scalar as Scalar;
use PHPParser_Node_Scalar_Encapsed as EncapsedString;
use PHPParser_Node_Expr_ConstFetch as ConstFetch;
use Psy\Exception\FatalErrorException;

/**
 * Validate that the instanceof statement does not receive a scalar value or a non-class constant.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class InstanceOfPass extends CodeCleanerPass
{
    /**
     * Validate that the instanceof statement does not receive a scalar value or a non-class constant.
     *
     * @throws FatalErrorException if a scalar or a non-class constant is given
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if (!$node instanceof InstanceOfNode) {
            return;
        }

        if (($node->expr instanceof Scalar && !$node->expr instanceof EncapsedString) || $node->expr instanceof ConstFetch) {
            throw new FatalErrorException('instanceof expects an object instance, constant given');
        }
    }
}
