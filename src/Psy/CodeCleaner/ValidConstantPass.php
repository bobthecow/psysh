<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PHPParser_Node_Expr_ConstFetch as ConstantFetch;
use Psy\CodeCleaner\NamespaceAwarePass;
use Psy\Exception\FatalErrorException;

/**
 * Validate that namespaced constant references will succeed.
 *
 * This pass throws a FatalErrorException rather than letting PHP run
 * headfirst into a real fatal error and die.
 *
 * @todo Detect constants defined in the current code snippet?
 *       ... Might not be worth it, since it would need to both be defining and
 *       referencing a namespaced constant, which doesn't seem like that big of
 *       a target for failure.
 */
class ValidConstantPass extends NamespaceAwarePass
{
    /**
     * Validate that namespaced constant references will succeed.
     *
     * Note that this does not (yet) detect constants defined in the current code
     * snippet. It won't happen very often, so we'll punt for now.
     *
     * @throws FatalErrorException if a constant reference is not defined.
     *
     * @param mixed &$stmt
     */
    protected function processStatement(&$stmt)
    {
        parent::processStatement($stmt);
        if ($stmt instanceof ConstantFetch && count($stmt->name->parts) > 1) {
            $name = $this->getFullyQualifiedName($stmt->name);
            if (!defined($name)) {
                throw new FatalErrorException(sprintf('Undefined constant %s', $name), 0, 1, null, $stmt->getLine());
            }
        }
    }
}
