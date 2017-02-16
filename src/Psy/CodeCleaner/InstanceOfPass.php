<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Instanceof_ as InstanceofStmt;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\Encapsed;
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
        if (!$node instanceof InstanceofStmt) {
            return;
        }

        if (($node->expr instanceof Scalar && !$node->expr instanceof Encapsed) || $node->expr instanceof ConstFetch) {
            throw new FatalErrorException('instanceof expects an object instance, constant given');
        }
    }
}
