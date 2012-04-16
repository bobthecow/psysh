<?php

namespace Psy\Command;

use Psy\Command\ShellAwareCommand;
use Psy\Exception\RuntimeException;
use Psy\Formatter\DocblockFormatter;
use Psy\Util\Docblock;
use Psy\Util\Mirror;
use Psy\Util\Inspector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An abstract command with helpers for inspecting the current shell scope.
 */
abstract class ReflectingCommand extends ShellAwareCommand
{
    const CLASS_OR_FUNC     = '/^[\\\\\w]+$/';
    const INSTANCE          = '/^\$(\w+)$/';
    const CLASS_MEMBER      = '/^([\\\\\w]+)::(\w+)$/';
    const CLASS_STATIC      = '/^([\\\\\w]+)::\$(\w+)$/';
    const INSTANCE_MEMBER   = '/^\$(\w+)(::|->)(\w+)$/';
    const INSTANCE_STATIC   = '/^\$(\w+)::\$(\w+)$/';

    protected function getInstance($valueName)
    {
        throw new \RuntimeException('deprecated');

        $valueName = trim($valueName);
        $matches   = array();
        switch (true) {
            case preg_match(self::CLASS_OR_FUNC, $valueName, $matches):
                return Inspector::getReflectionClass($matches[0]);
            case preg_match(self::INSTANCE, $valueName, $matches):
                $value = $this->resolveInstance($matches[1]);
                if (is_object($value)) {
                    return Inspector::getReflectionClass($value);
                } else {
                    return $value;
                }
            default:
                throw new \InvalidArgumentException('Unknown target: '.$valueName);
        }
    }

    /**
     * Get the target for a value.
     *
     * @throws \InvalidArgumentException when the value specified can't be resolved.
     *
     * @param string $valueName Function, class, variable, constant, method or property name.
     *
     * @return array (class or instance name, member name, kind)
     */
    protected function getTarget($valueName, $classOnly = false)
    {
        $valueName = trim($valueName);
        $matches   = array();
        switch (true) {
            case preg_match(self::CLASS_OR_FUNC, $valueName, $matches):
                return array($matches[0], null, 0);

            case preg_match(self::INSTANCE, $valueName, $matches):
                return array($this->resolveInstance($matches[1]), null, 0);

            case (!$classOnly && preg_match(self::CLASS_MEMBER, $valueName, $matches)):
                return array($matches[1], $matches[2], Mirror::CONSTANT | Mirror::METHOD);

            case (!$classOnly && preg_match(self::CLASS_STATIC, $valueName, $matches)):
                return array($matches[1], $matches[2], Mirror::STATIC_PROPERTY | Mirror::PROPERTY);

            case (!$classOnly && preg_match(self::INSTANCE_MEMBER, $valueName, $matches)):
                if ($matches[2] == '->') {
                    $kind = Mirror::METHOD | Mirror::PROPERTY;
                } else {
                    $kind = Mirror::CONSTANT | Mirror::METHOD;
                }

                return array($this->resolveInstance($matches[1]), $matches[3], $kind);

            case (!$classOnly && preg_match(self::INSTANCE_STATIC, $valueName, $matches)):
                return array($this->resolveInstance($matches[1]), $matches[2], Inspector::STATIC_PROPERTY);

            default:
                throw new RuntimeException('Unknown target: '.$valueName);
        }
    }

    /**
     * Get a Reflector and documentation for a function, class or instance, constant, method or property.
     *
     * @param string $valueName Function, class, variable, constant, method or property name.
     *
     * @return array (value, Reflector)
     */
    protected function getTargetAndReflector($valueName, $classOnly = false)
    {
        list ($value, $member, $kind) = $this->getTarget($valueName, $classOnly);

        return array($value, Mirror::get($value, $member, $kind));
    }

    /**
     * Return a variable instance from the current scope.
     *
     * @throws \InvalidArgumentException when the requested variable does not exist in the current scope.
     *
     * @param string $value
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

    protected function getScopeVariable($name)
    {
        return $this->shell->getScopeVariable($name);
    }

    protected function getScopeVariables()
    {
        return $this->shell->getScopeVariables();
    }
}
