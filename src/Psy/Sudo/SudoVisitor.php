<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Sudo;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name\FullyQualified as FullyQualifiedName;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

/**
 * A PHP Parser node visitor which rewrites property and method access to use
 * the Psy\Sudo visibility bypass methods.
 *
 * @todo handle assigning by reference
 */
class SudoVisitor extends NodeVisitorAbstract
{
    const SUDO_CLASS = 'Psy\Sudo';

    const PROPERTY_FETCH         = 'fetchProperty';
    const PROPERTY_ASSIGN        = 'assignProperty';
    const METHOD_CALL            = 'callMethod';
    const STATIC_PROPERTY_FETCH  = 'fetchStaticProperty';
    const STATIC_PROPERTY_ASSIGN = 'assignStaticProperty';
    const STATIC_CALL            = 'callStatic';
    const CLASS_CONST_FETCH      = 'fetchClassConst';

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof PropertyFetch) {
            $args = array(
                $node->var,
                is_string($node->name) ? new String_($node->name) : $node->name,
            );

            return $this->prepareCall(self::PROPERTY_FETCH, $args);
        } elseif ($node instanceof Assign && $node->var instanceof PropertyFetch) {
            $target = $node->var;
            $args = array(
                $target->var,
                is_string($target->name) ? new String_($target->name) : $target->name,
                $node->expr,
            );

            return $this->prepareCall(self::PROPERTY_ASSIGN, $args);
        } elseif ($node instanceof MethodCall) {
            $args = $node->args;
            array_unshift($args, new Arg(is_string($node->name) ? new String_($node->name) : $node->name));
            array_unshift($args, new Arg($node->var));

            // not using prepareCall because the $node->args we started with are already Arg instances
            return new StaticCall(new FullyQualifiedName(self::SUDO_CLASS), self::METHOD_CALL, $args);
        } elseif ($node instanceof StaticPropertyFetch) {
            $class = $node->class instanceof Name ? (string) $node->class : $node->class;
            $args = array(
                is_string($class) ? new String_($class) : $class,
                is_string($node->name) ? new String_($node->name) : $node->name,
            );

            return $this->prepareCall(self::STATIC_PROPERTY_FETCH, $args);
        } elseif ($node instanceof Assign && $node->var instanceof StaticPropertyFetch) {
            $target = $node->var;
            $class = $target->class instanceof Name ? (string) $target->class : $target->class;
            $args = array(
                is_string($class) ? new String_($class) : $class,
                is_string($target->name) ? new String_($target->name) : $target->name,
                $node->expr,
            );

            return $this->prepareCall(self::STATIC_PROPERTY_ASSIGN, $args);
        } elseif ($node instanceof StaticCall) {
            $args = $node->args;
            $class = $node->class instanceof Name ? (string) $node->class : $node->class;
            array_unshift($args, new Arg(is_string($node->name) ? new String_($node->name) : $node->name));
            array_unshift($args, new Arg(is_string($class) ? new String_($class) : $class));

            // not using prepareCall because the $node->args we started with are already Arg instances
            return new StaticCall(new FullyQualifiedName(self::SUDO_CLASS), self::STATIC_CALL, $args);
        } elseif ($node instanceof ClassConstFetch) {
            $class = $node->class instanceof Name ? (string) $node->class : $node->class;
            $args = array(
                is_string($class) ? new String_($class) : $class,
                is_string($node->name) ? new String_($node->name) : $node->name,
            );

            return $this->prepareCall(self::CLASS_CONST_FETCH, $args);
        }
    }

    private function prepareCall($method, $args)
    {
        return new StaticCall(new FullyQualifiedName(self::SUDO_CLASS), $method, array_map(function ($arg) {
            return new Arg($arg);
        }, $args));
    }
}
