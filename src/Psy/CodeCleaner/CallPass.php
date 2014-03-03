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
use PHPParser_Node_Arg as Argument;
use PHPParser_Node_Expr_Array as ArrayNode;
use PHPParser_Node_Expr_ArrayItem as ArrayItem;
use PHPParser_Node_Expr_ConstFetch as ConstantFetch;
use PHPParser_Node_Expr_ErrorSuppress as ErrorSuppress;
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Expr_Isset as IssetNode;
use PHPParser_Node_Expr_MethodCall as MethodCall;
use PHPParser_Node_Expr_New as NewNode;
use PHPParser_Node_Expr_StaticCall as StaticCall;
use PHPParser_Node_Expr_Ternary as Ternary;
use PHPParser_Node_Expr_Variable as Variable;
use PHPParser_Node_Name as Name;
use PHPParser_Node_Scalar as Scalar;
use PHPParser_Node_Scalar_ClassConst as ClassConst;
use PHPParser_Node_Scalar_String as String;
use PHPParser_Node_Stmt_Class as ClassStatement;
use PHPParser_Node_Stmt_Function as FunctionStatement;
use Psy\Exception\FatalErrorException;

/**
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class CallPass extends NamespaceAwarePass
{
    public function leaveNode(Node $node)
    {
        if ($node instanceof FunctionStatement) {
            $this->currentScope['function'][strtolower($this->getFullyQualifiedName($node->name))] = true;
        } elseif ($node instanceof ClassStatement) {
            $this->currentScope['class'][strtolower($this->getFullyQualifiedName($node->name))] = true;
        }

        if (!$node instanceof FunctionCall && !$node instanceof MethodCall && !$node instanceof StaticCall){
            return;
        }

        $args = $this->parseArguments($node->args);

        if ($node instanceof FunctionCall) {
            array_unshift($args, new Argument($this->getName($node->name, 'function')));
            $method = 'callFunction';
        } elseif ($node instanceof MethodCall) {
            if ($node->var instanceof Variable && 'this' == $node->var->name) {
                $var = new Ternary(new IssetNode(array($node->var)), $node->var, new String('this'));
            } else {
                $var = $node->var;
            }
            array_unshift($args, new Argument($var), new Argument(is_string($node->name) ? new String($node->name) : $node->name));
            $method = 'callMethod';
        } elseif ($node instanceof StaticCall) {
            array_unshift($args, new Argument($this->getName($node->class, 'class')), new Argument(is_string($node->name) ? new String($node->name) : $node->name));
            $method = 'callStatic';
        }

        $args[] = new Argument(new ClassConst());
        $args[] = new Argument(new ErrorSuppress(new FunctionCall(new Name('get_called_class'))));

        return new StaticCall(new Name('\\'.__CLASS__), $method, $args);
    }

    /**
     * Calls a function
     *
     * @param string      $function   The function name
     * @param array       $args       The function arguments
     * @param array       $references The variable references
     * @param string      $declared   The name of declared class
     * @param string|bool $called     The name of called class
     *
     * @throws FatalErrorException if a function is not callable
     * @throws FatalErrorException if a function is undefined
     * @throws FatalErrorException if a non-variable is passed by reference
     *
     * @return mixed
     */
    public static function &callFunction($function, array $args = array(), array $references = array(), $declared = '', $called = false)
    {
        if (is_array($function) && 2 == count($function) && version_compare(PHP_VERSION, '5.4', '>=')) {
            if (!array_key_exists(0, $function) || !array_key_exists(1, $function)) {
                // PHP < 5.4.8 exits with code 139
                throw new FatalErrorException('Array callback has to contain indices 0 and 1');
            }

            if (is_string($function[0])) {
                $method = 'callStatic';
            } elseif (is_object($function[0])) {
                $method = 'callMethod';
            } else {
                throw new FatalErrorException('First array member is not a valid class name or object');
            }

            if (!is_string($function[1])) {
                throw new FatalErrorException('Second array member is not a valid method');
            }

            return static::$method($function[0], $function[1], $args, $references, $declared, $called);
        }

        if (!is_callable($function) || is_array($function)) {
            throw new FatalErrorException('Function name must be a string');
        }

        if (is_object($function) && !$function instanceof \Closure) {
            $ref = new \ReflectionMethod($function, '__invoke');
        } else {
            try {
                $ref = new \ReflectionFunction($function);
            } catch (\ReflectionException $e) {
                throw new FatalErrorException(sprintf('Call to undefined function %s()', $function));
            }
        }

        $value = call_user_func_array($function, static::processParameters($ref, $args, $references));

        return $value;
    }

    /**
     * Calls a method
     *
     * @param object      $object     The object
     * @param string      $method     The method name
     * @param array       $args       The function arguments
     * @param array       $references The variable references
     * @param string      $declared   The name of declared class
     * @param string|bool $called     The name of called class
     *
     * @throws FatalErrorException if a function is not callable
     * @throws FatalErrorException if a function is undefined
     * @throws FatalErrorException if a non-variable is passed by reference
     *
     * @return mixed
     */
    public static function &callMethod($object, $method, array $args = array(), array $references = array(), $declared = '', $called = false)
    {
        if (!is_string($method)) {
            throw new FatalErrorException('Method name must be a string');
        }

        if ('this' === $object) {
            throw new FatalErrorException('Using $this when not in object context');
        } elseif (!is_object($object)) {
            throw new FatalErrorException('Call to a member function '.$method.'() on a non-object');
        }

        return static::processCall(new \ReflectionClass($object), $method, $args, $references, $called, $object);
    }

    /**
     * Calls a static method
     *
     * @param string      $class       The class name
     * @param string      $method     The name of method
     * @param array       $args       The function arguments
     * @param array       $references The variable references
     * @param string      $declared   The name of declared class
     * @param string|bool $called     The name of called class
     *
     * @throws FatalErrorException if a function is not callable
     * @throws FatalErrorException if a function is undefined
     * @throws FatalErrorException if a non-variable is passed by reference
     *
     * @return mixed
     */
    public static function &callStatic($class, $method, array $args = array(), array $references = array(), $declared = '', $called = false)
    {
        if ('self' === $class) {
            if ('' === $declared) {
                throw new FatalErrorException('Cannot access self:: when no class scope is active');
            }

            $ref = new \ReflectionClass($declared);
        } elseif ('static' === $class) {
            if (false === $called) {
                throw new FatalErrorException('Cannot access static:: when no class scope is active');
            }

            $ref = new \ReflectionClass($called);
        } elseif ('parent' === $declared) {
            if (false === $called) {
                throw new FatalErrorException('Cannot access static:: when no class scope is active');
            } elseif (false === get_parent_class($called)) {
                throw new FatalErrorException('Cannot access parent:: when current class scope has no parent');
            }

            $ref = new \ReflectionClass(get_parent_class($called));
        } elseif (!class_exists($class)) {
            throw new FatalErrorException(sprintf("Class '%s' not found", $class));
        } else {
            $ref = new \ReflectionClass($class);
        }

        return static::processCall($ref, $method, $args, $references, $called, null);
    }

    protected static function &processCall(\ReflectionClass $refClass, $method, $args, $references, $called, $object)
    {
        $magicMethod = $object ? '__call' : '__callStatic';

        if ($refClass->hasMethod($method)) {
            $refMethod = $refClass->getMethod($method);
        } elseif ($refClass->hasMethod($magicMethod)) {
            $refMethod = $refClass->getMethod($magicMethod);
            $args = array($method, $args);
            $references = array();
        } else {
            throw new FatalErrorException(sprintf('Call to undefined method %s::%s()', $refClass->name, $method));
        }

        if (!$object && !$refMethod->isStatic()) {
            // this should be Strict standards error
            trigger_error(sprintf('Non-static method %s::%s() should not be called statically', $refMethod->getShortName(), $method), E_USER_NOTICE);
        }

        if (!$refMethod->isPublic() && $magicMethod !== $refMethod->name) {
            $refCalled = false !== $called ? new \ReflectionClass($called) : null;

            if (!$refCalled || $refCalled->name !== $refMethod->class && $refMethod->isPrivate() && $refCalled->isSubclassOf($refMethod->class)) {
                throw new FatalErrorException(sprintf(
                    "Call to %s method %s::{$method}() from context '%s'",
                    $refMethod->isProtected() ? 'protected' : 'private', $refMethod->getDeclaringClass()->name, $called ?: ''
                ));
            }
        }

        $refMethod->setAccessible(true);

        $value = $refMethod->invokeArgs($object, static::processParameters($refMethod, $args, $references));

        return $value;
    }

    protected static function processParameters(\ReflectionFunctionAbstract $ref, array $args, array $references)
    {
        $parameters = array();
        foreach ($ref->getParameters() as $i => $parameter) {
            if (!array_key_exists($i, $args)) {
                break;
            }

            if ($parameter->isPassedByReference()) {
                if (false === $references[$i] && ($ref->inNamespace() || $ref instanceof \ReflectionMethod || $ref->isClosure())) {
                    throw new FatalErrorException(sprintf('Cannot pass parameter %d by reference', $i + 1));
                } elseif (false === $references[$i]) {
                    throw new FatalErrorException('Only variables can be passed by reference');
                } elseif (null === $references[$i]) {
                    // this should be Strict standards error
                    trigger_error('Only variables should be passed by reference', E_USER_NOTICE);
                }

                $parameters[] = &$args[$i];
            } else {
                $parameters[] = $args[$i];
            }
        }

        return $parameters + $args;
    }

    protected function getName(Node $name, $type)
    {
        if ($name instanceof Name) {
            $test = $type.'_exists';

            $shortName = implode('\\', $name->parts);
            $fullName  = $this->getFullyQualifiedName($name);

            if (isset($this->currentScope[$type][strtolower($fullName)]) && $test($fullName)
                || !$test($shortName) && !in_array($shortName, array('parent', 'self', 'static'), true)
            ) {
                $name = new String((string) $fullName);
            } else {
                $name = new String((string) $shortName);
            }
        }

        return $name;
    }

    protected function parseArguments(array $args)
    {
        $arguments = array();
        $references = array();
        foreach ($args as $arg) {
            $value = $arg->value;
            if ($value instanceof Variable || $value instanceof NewNode) {
                $reference = 'true';
            } elseif (!$value instanceof Scalar && !$value instanceof ArrayNode) {
                $reference = 'null';
            } else {
                $reference = 'false';
            }

            $arguments[] = new ArrayItem($value, null, $value instanceof Variable);
            $references[] = new ArrayItem(new ConstantFetch(new Name($reference)));
        }

        return array(new Argument(new ArrayNode($arguments)), new Argument(new ArrayNode($references)));
    }
}
