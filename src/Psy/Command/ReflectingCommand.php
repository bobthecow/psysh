<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Context;
use Psy\ContextAware;
use Psy\Exception\RuntimeException;
use Psy\Util\Mirror;

/**
 * An abstract command with helpers for inspecting the current context.
 */
abstract class ReflectingCommand extends Command implements ContextAware
{
    const CLASS_OR_FUNC   = '/^[\\\\\w]+$/';
    const INSTANCE        = '/^\$(\w+)$/';
    const CLASS_MEMBER    = '/^([\\\\\w]+)::(\w+)$/';
    const CLASS_STATIC    = '/^([\\\\\w]+)::\$(\w+)$/';
    const INSTANCE_MEMBER = '/^\$(\w+)(::|->)(\w+)$/';
    const INSTANCE_STATIC = '/^\$(\w+)::\$(\w+)$/';

    /**
     * Context instance (for ContextAware interface).
     *
     * @var Context
     */
    protected $context;

    /**
     * ContextAware interface.
     *
     * @param Context $context
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Get the target for a value.
     *
     * @throws \InvalidArgumentException when the value specified can't be resolved.
     *
     * @param string $valueName Function, class, variable, constant, method or property name.
     * @param bool   $classOnly True if the name should only refer to a class, function or instance
     *
     * @return array (class or instance name, member name, kind)
     */
    protected function getTarget($valueName, $classOnly = false)
    {
        $valueName = trim($valueName);
        $matches   = array();
        switch (true) {
            case preg_match(self::CLASS_OR_FUNC, $valueName, $matches):
                return array($this->resolveName($matches[0], true), null, 0);

            case preg_match(self::INSTANCE, $valueName, $matches):
                return array($this->resolveInstance($matches[1]), null, 0);

            case (!$classOnly && preg_match(self::CLASS_MEMBER, $valueName, $matches)):
                return array($this->resolveName($matches[1]), $matches[2], Mirror::CONSTANT | Mirror::METHOD);

            case (!$classOnly && preg_match(self::CLASS_STATIC, $valueName, $matches)):
                return array($this->resolveName($matches[1]), $matches[2], Mirror::STATIC_PROPERTY | Mirror::PROPERTY);

            case (!$classOnly && preg_match(self::INSTANCE_MEMBER, $valueName, $matches)):
                if ($matches[2] === '->') {
                    $kind = Mirror::METHOD | Mirror::PROPERTY;
                } else {
                    $kind = Mirror::CONSTANT | Mirror::METHOD;
                }

                return array($this->resolveInstance($matches[1]), $matches[3], $kind);

            case (!$classOnly && preg_match(self::INSTANCE_STATIC, $valueName, $matches)):
                return array($this->resolveInstance($matches[1]), $matches[2], Mirror::STATIC_PROPERTY);

            default:
                throw new RuntimeException('Unknown target: ' . $valueName);
        }
    }

    /**
     * Resolve a class or function name (with the current shell namespace).
     *
     * @param string $name
     * @param bool   $includeFunctions (default: false)
     *
     * @return string
     */
    protected function resolveName($name, $includeFunctions = false)
    {
        if (substr($name, 0, 1) === '\\') {
            return $name;
        }

        if ($namespace = $this->getApplication()->getNamespace()) {
            $fullName = $namespace . '\\' . $name;

            if (class_exists($fullName) || interface_exists($fullName) || ($includeFunctions && function_exists($fullName))) {
                return $fullName;
            }
        }

        return $name;
    }

    /**
     * Get a Reflector and documentation for a function, class or instance, constant, method or property.
     *
     * @param string $valueName Function, class, variable, constant, method or property name.
     * @param bool   $classOnly True if the name should only refer to a class, function or instance
     *
     * @return array (value, Reflector)
     */
    protected function getTargetAndReflector($valueName, $classOnly = false)
    {
        list($value, $member, $kind) = $this->getTarget($valueName, $classOnly);

        return array($value, Mirror::get($value, $member, $kind));
    }

    /**
     * Return a variable instance from the current scope.
     *
     * @throws \InvalidArgumentException when the requested variable does not exist in the current scope.
     *
     * @param string $name
     *
     * @return mixed Variable instance.
     */
    protected function resolveInstance($name)
    {
        $value = $this->getScopeVariable($name);
        if (!is_object($value)) {
            throw new RuntimeException('Unable to inspect a non-object');
        }

        return $value;
    }

    /**
     * Get a variable from the current shell scope.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getScopeVariable($name)
    {
        return $this->context->get($name);
    }

    /**
     * Get all scope variables from the current shell scope.
     *
     * @return array
     */
    protected function getScopeVariables()
    {
        return $this->context->getAll();
    }
}
