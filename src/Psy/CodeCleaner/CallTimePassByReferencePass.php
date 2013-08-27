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

use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Expr_MethodCall as MethodCall;
use PHPParser_Node_Expr_StaticCall as StaticCall;
use Psy\Exception\FatalErrorException;

/**
 * Validate that the user did not use the call-time pass-by-reference that causes a fatal error.
 *
 * As of PHP 5.4.0, call-time pass-by-reference was removed, so using it will raise a fatal error.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class CallTimePassByReferencePass extends CodeCleanerPass
{
    /**
     * Validate of use call-time pass-by-reference.
     *
     * @throws RuntimeException if the user used call-time pass-by-reference in PHP >= 5.4.0
     *
     * @param mixed &$stmt PHPParser statement
     */
    protected function processStatement(&$stmt)
    {
        if (version_compare(PHP_VERSION, '5.4', '<')) {
            return;
        }

        if (!$stmt instanceof FunctionCall && !$stmt instanceof MethodCall && !$stmt instanceof StaticCall) {
            return;
        }

        foreach ($stmt->args as $arg) {
            if ($arg->byRef) {
                throw new FatalErrorException('Call-time pass-by-reference has been removed');
            }
        }
    }
}
