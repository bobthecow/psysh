<?php

namespace Psy\CodeCleaner;

use PhpParser\Node;

class RequirePass extends CodeCleanerPass
{
    public function enterNode(Node $node)
    {
        if ((
            $node instanceof Node\Expr\Include_ &&
            in_array($node->type, array(3, 4)) &&
            !$node->getAttribute('included')
        )) {
            $injected = clone $node;
            $injected->setAttribute('included', true);

            return new Node\Expr\FuncCall(
                new Node\Name('call_user_func'),
                array(
                    new Node\Arg(
                        new Node\Expr\Closure(
                            array(
                                'params' => array(new Node\Param('class')),
                                'stmts' => array(
                                    new Node\Stmt\If_(
                                        new Node\Expr\FuncCall(
                                            new Node\Name('stream_resolve_include_path'),
                                            array(new Node\Arg(new Node\Expr\Variable('class')))
                                        ),
                                        array(
                                            'stmts' => array(new Node\Stmt\Return_($injected)),
                                            'else' => new Node\Stmt\Else_(
                                                array(
                                                    new Node\Expr\FuncCall(
                                                        new Node\Name('trigger_error'),
                                                        array(
                                                            new Node\Expr\FuncCall(
                                                                new Node\Name('sprintf'),
                                                                array(
                                                                    new Node\Arg(new Node\Scalar\String('require(%s): failed to open stream: No such file or directory in php shell code on line %d')),
                                                                    new Node\Arg(new Node\Expr\Variable('class')),
                                                                    new Node\Arg(new Node\Scalar\DNumber($node->getLine())),
                                                                )
                                                            ),
                                                        )
                                                    ),
                                                )
                                            ),
                                        )
                                    ),
                                ),
                            )
                        )
                    ),
                    new Node\Arg($node->expr),
                ),
                $node->getAttributes()
            );
        }
    }
}
