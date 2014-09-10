<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

/**
 * The Shell execution context.
 *
 * This class encapsulates the current variables, most recent return value and
 * exception, and the current namespace.
 */
class Context
{
    private static $specialVars = array('_', '_e', '__psysh__');
    private $scopeVariables = array();
    private $lastException;
    private $returnValue;

    /**
     * Get a context variable.
     *
     * @throws InvalidArgumentException If the variable is not found in the current context.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function get($name)
    {
        switch ($name) {
            case '_':
                return $this->returnValue;

            case '_e':
                if (!isset($this->lastException)) {
                    throw new \InvalidArgumentException('Unknown variable: $' . $name);
                }

                return $this->lastException;

            default:
                if (!array_key_exists($name, $this->scopeVariables)) {
                    throw new \InvalidArgumentException('Unknown variable: $' . $name);
                }

                return $this->scopeVariables[$name];
        }
    }

    /**
     * Get all defined variables.
     *
     * @return array
     */
    public function getAll()
    {
        $vars = $this->scopeVariables;
        $vars['_'] = $this->returnValue;

        if (isset($this->lastException)) {
            $vars['_e'] = $this->lastException;
        }

        return $vars;
    }

    /**
     * Set all scope variables.
     *
     * This method does *not* set the magic $_ and $_e variables.
     *
     * @param array $vars
     */
    public function setAll(array $vars)
    {
        foreach (self::$specialVars as $key) {
            unset($vars[$key]);
        }

        $this->scopeVariables = $vars;
    }

    /**
     * Set the most recent return value.
     *
     * @param mixed $value
     */
    public function setReturnValue($value)
    {
        $this->returnValue = $value;
    }

    /**
     * Get the most recent return value.
     *
     * @return mixed
     */
    public function getReturnValue()
    {
        return $this->returnValue;
    }

    /**
     * Set the most recent Exception.
     *
     * @param \Exception $e
     */
    public function setLastException(\Exception $e)
    {
        $this->lastException = $e;
    }

    /**
     * Get the most recent Exception.
     *
     * @throws InvalidArgumentException If no Exception has been caught.
     *
     * @return null|Exception
     */
    public function getLastException()
    {
        if (!isset($this->lastException)) {
            throw new \InvalidArgumentException('No most-recent exception');
        }

        return $this->lastException;
    }
}
