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
 * Code buffer, keeps track of inputed code.
 *
 * @author volter9
 */
class CodeBuffer
{
    // Characters
    private $openOperator = '\\';
    private $oneLevel  = '/(?<!\\\\)(\'|")/';
    // private $levelUp   = '/\{|\[|\(/';
    // private $levelDown = '/\}|\]|\)/';

    private $buffer;
    private $open;

    private $depth;
    private $inverse;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * Return array of lines of inputed code.
     *
     * @return array
     */
    public function getBuffer()
    {
        return $this->buffer;
    }

    /**
     * Get depth of input code expressions.
     *
     * @return int
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * Checks if the code buffer is open (the code isn't complited).
     *
     * @return bool
     */
    public function isOpen()
    {
        return $this->open && $this->depth > 0;
    }

    /**
     * Checks whether code buffer is.
     */
    public function isEmpty()
    {
        return empty($this->buffer);
    }

    /**
     * Adds code to the buffer.
     *
     * @param string $code
     */
    public function addCode($code)
    {
        $this->computeDepthSum($code);
        $this->processCode($code);
    }

    /**
     * Computes depth sum for keeping buffer open for opening/closening:
     * brackets, square brackets and paranthesis.
     *
     * @param string $code
     */
    protected function computeDepthSum($code)
    {
        $one  = $this->countByRegEx($this->oneLevel, $code);
        // $up   = $this->countByRegEx($this->levelUp, $code);
        // $down = $this->countByRegEx($this->levelDown, $code);

        $inverse = $this->inverse = (bool) ($this->inverse ^ $one % 2 === 1);
        $factor = $inverse ? 1 : -1;

        $this->depth += ($one % 2) * $factor; // + $up - $down;
    }

    private function countByRegEx($regEx, $string)
    {
        preg_match_all($regEx, $string, $matches);

        return isset($matches[1]) ? count($matches[1]) : 0;
    }

    /**
     * Processes the code and processes opening buffer operator '\'.
     *
     * @param string $code
     */
    protected function processCode($code)
    {
        $lastCharacter = substr(rtrim($code), -1);

        if ($lastCharacter === $this->openOperator) {
            $this->open = true;
            $code = substr(rtrim($code), 0, -1);
        } elseif ($this->depth >= 0) {
            $this->open = true;
        } elseif ($this->depth <= 0) {
            $this->open = false;
        }

        if ($lastCharacter === ';') {
            $this->open = false;
            $this->depth = 0;
        }

        $this->buffer[] = $code;
    }

    /**
     * Close the code buffer.
     */
    public function close()
    {
        $this->open = false;
    }

    /**
     * Resets code buffer by setting its properties to default values.
     */
    public function reset()
    {
        $this->buffer = array();
        $this->open = false;
        $this->depth = 0;
    }
}
