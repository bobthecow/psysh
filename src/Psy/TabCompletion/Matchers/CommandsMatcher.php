<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\ClassNamesRuler;

/**
 * Class CommandsMatcher
 * @package Psy\TabCompletion\Matchers
 */
class CommandsMatcher extends AbstractMatcher
{
    /** @var array  */
    protected $commands = array();

    protected function buildRules()
    {
        $this->rules[] = new ClassNamesRuler();
    }

    /**
     * @param $commands
     */
    public function setCommands($commands)
    {
        $this->commands = $commands;
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);

        return array_filter($this->commands, function ($command) use ($input) {
            return AbstractMatcher::startsWith($input, $command);
        });
    }
}
