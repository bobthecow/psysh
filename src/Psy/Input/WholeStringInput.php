<?php

namespace Psy\Input;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\StringInput;

/**
 * Class WholeStringInput
 * @package Psy\Input
 */
class WholeStringInput extends StringInput
{
    /**
     * @var string
     */
    protected $input;

    /**
     * @var string
     */
    protected $line;

    /**
     * @param string          $input
     * @param InputDefinition $definition
     */
    public function __construct($input, InputDefinition $definition = null)
    {
        $this->input = $input;
        $command = reset(explode(" ", $this->input));
        $this->line = trim(str_replace($command, '', $this->input));

        parent::__construct($input, $definition);
    }

    /**
     * @return string
     */
    public function getInputLine()
    {
        return $this->input;
    }

    /**
     * @return string
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * Binds the current Input instance with the given arguments and options.
     *
     * @param InputDefinition $definition A InputDefinition instance
     */
    public function bind(InputDefinition $definition)
    {
        $this->setTokens(array());
        parent::parse();
    }
}
