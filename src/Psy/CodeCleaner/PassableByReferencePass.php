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
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use Psy\Exception\FatalErrorException;

/**
 * Validate that only variables (and variable-like things) are passed by reference.
 */
class PassableByReferencePass extends CodeCleanerPass
{
    const EXCEPTION_MESSAGE = 'Only variables can be passed by reference';

    /**
     * @throws FatalErrorException if non-variables are passed by reference
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        // @todo support MethodCall and StaticCall as well.
        if ($node instanceof FuncCall) {
            $name = $node->name;

            // if function name is an expression or a variable, give it a pass for now.
            if ($name instanceof Expr || $name instanceof Variable) {
                return;
            }

            try {
                $refl = new \ReflectionFunction(implode('\\', $name->parts));
            } catch (\ReflectionException $e) {
                // Well, we gave it a shot!
                return;
            }

            foreach ($refl->getParameters() as $key => $param) {
                if (array_key_exists($key, $node->args)) {
                    $arg = $node->args[$key];
                    if ($param->isPassedByReference() && !$this->isPassableByReference($arg)) {
                        throw new FatalErrorException(self::EXCEPTION_MESSAGE);
                    }
                }
            }
        }
    }

    private function isPassableByReference(Node $arg)
    {
        // FuncCall, MethodCall and StaticCall are all PHP _warnings_ not fatal errors, so we'll let
        // PHP handle those ones :)
        return $arg->value instanceof ClassConstFetch ||
            $arg->value instanceof PropertyFetch ||
            $arg->value instanceof Variable ||
            $arg->value instanceof FuncCall ||
            $arg->value instanceof MethodCall ||
            $arg->value instanceof StaticCall;
    }
}
