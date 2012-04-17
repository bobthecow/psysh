<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Reflection;

class ReflectionConstant implements \Reflector
{
    private $class;
    private $name;
    private $value;

    public function __construct(\ReflectionClass $class, $name)
    {
        $this->class = $class;
        $this->name  = $name;

        $constants = $class->getConstants();
        if (!array_key_exists($name, $constants)) {
            throw new \InvalidArgumentException('Unknown constant: '.$name);
        }

        $this->value = $constants[$name];
    }

    public function getDeclaringClass()
    {
        return $this->class;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getFileName()
    {
        return null;
        // return $this->class->getFileName();
    }

    public function getStartLine()
    {
        throw new \Exception('Not yet implemented because it\'s unclear what I should do here :)');
    }

    public function getEndLine()
    {
        return $this->getStartLine();
    }

    public function getDocComment()
    {
        return null;
    }

    public static function export()
    {
        throw new \Exception('Not yet implemented because it\'s unclear what I should do here :)');
    }

    public function __toString()
    {
        return $this->getName();
    }
}
