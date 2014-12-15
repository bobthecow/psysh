<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Reflection;

/**
 * Somehow the standard reflection library doesn't include constants.
 *
 * ReflectionConstant corrects that omission.
 */
class ReflectionConstant implements \Reflector
{
    private $class;
    private $name;
    private $value;

    /**
     * Construct a ReflectionConstant object.
     *
     * @param mixed  $class
     * @param string $name
     */
    public function __construct($class, $name)
    {
        if (! $class instanceof \ReflectionClass) {
            $class = new \ReflectionClass($class);
        }

        $this->class = $class;
        $this->name  = $name;

        $constants = $class->getConstants();
        if (!array_key_exists($name, $constants)) {
            throw new \InvalidArgumentException('Unknown constant: ' . $name);
        }

        $this->value = $constants[$name];
    }

    /**
     * Gets the declaring class.
     *
     * @return string
     */
    public function getDeclaringClass()
    {
        return $this->class;
    }

    /**
     * Gets the constant name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the value of the constant.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Gets the constant's file name.
     *
     * Currently returns null, because if it returns a file name the signature
     * formatter will barf.
     *
     * @return null
     */
    public function getFileName()
    {
        return;
        // return $this->class->getFileName();
    }

    /**
     * Get the code start line.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function getStartLine()
    {
        throw new \RuntimeException('Not yet implemented because it\'s unclear what I should do here :)');
    }

    /**
     * Get the code end line.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function getEndLine()
    {
        return $this->getStartLine();
    }

    /**
     * Get the constant's docblock.
     *
     * @return false
     */
    public function getDocComment()
    {
        return false;
    }

    /**
     * Export the constant? I don't think this is possible.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public static function export()
    {
        throw new \RuntimeException('Not yet implemented because it\'s unclear what I should do here :)');
    }

    /**
     * To string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}
