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

use Psy\Exception\FatalErrorException;
use PHPParser_Node_Expr_Assign as Assign;
use PHPParser_Node_Expr_Variable as Variable;

/**
 * Validate that the user input does not assign the `$this` variable.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class AssignThisVariablePass extends CodeCleanerPass
{
    /**
     * Validate that the user input does not assign the `$this` variable.
     *
     * @throws RuntimeException if the user assign the `$this` variable.
     *
     * @param mixed &$stmt PHPParser statement
     */
    protected function processStatement(&$stmt)
    {
        if ($stmt instanceof Assign && $stmt->var instanceof Variable && $stmt->var->name === 'this') {
            throw new FatalErrorException('Cannot re-assign $this');
        }
    }
}
