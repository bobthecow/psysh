<?php

namespace Psy\TabCompletion;

/**
 * Class CommandsMatcher
 * @package Psy\TabCompletion
 */
class CommandsMatcher extends AbstractMatcher
{
    protected $commands = array();

    public function setCommands($commands)
    {
        $this->commands = $commands;
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        return array_filter($this->commands, function ($command) use ($input, $index, $info) {
            return AbstractMatcher::startsWith($input, $command);
        });
    }
}
