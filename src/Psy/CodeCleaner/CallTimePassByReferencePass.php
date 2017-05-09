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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
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
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if (version_compare(PHP_VERSION, '5.4', '<')) {
            return;
        }

        if (!$node instanceof FuncCall && !$node instanceof MethodCall && !$node instanceof StaticCall) {
            return;
        }

        foreach ($node->args as $arg) {
            if ($arg->byRef) {
                throw new FatalErrorException('Call-time pass-by-reference has been removed');
            }
        }
    }
}
