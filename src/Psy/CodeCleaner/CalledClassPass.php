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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_ as ClassStmt;
use PhpParser\Node\Stmt\Trait_ as TraitStmt;
use Psy\Exception\ErrorException;

/**
 * The called class pass throws warnings for get_class() and get_called_class()
 * outside a class context.
 */
class CalledClassPass extends CodeCleanerPass
{
    private $inClass;

    /**
     * @param array $nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->inClass = false;
    }

    /**
     * @throws ErrorException if get_class or get_called_class is called without an object from outside a class
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof ClassStmt || $node instanceof TraitStmt) {
            $this->inClass = true;
        } elseif ($node instanceof FuncCall && !$this->inClass) {
            // We'll give any args at all (besides null) a pass.
            // Technically we should be checking whether the args are objects, but this will do for now.
            //
            // TODO: switch this to actually validate args when we get context-aware code cleaner passes.
            if (!empty($node->args) && !$this->isNull($node->args[0])) {
                return;
            }

            // We'll ignore name expressions as well (things like `$foo()`)
            if (!($node->name instanceof Name)) {
                return;
            }

            $name = strtolower($node->name);
            if (in_array($name, array('get_class', 'get_called_class'))) {
                $msg = sprintf('%s() called without object from outside a class', $name);
                throw new ErrorException($msg, 0, E_USER_WARNING, null, $node->getLine());
            }
        }
    }

    /**
     * @param Node $node
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof ClassStmt) {
            $this->inClass = false;
        }
    }

    private function isNull(Node $node)
    {
        return $node->value instanceof ConstFetch && strtolower($node->value->name) === 'null';
    }
}
