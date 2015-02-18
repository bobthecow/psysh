<?php

namespace Psy\TabCompletion\Matchers;

use Psy\Command\Command;

/**
 * Class CommandsMatcher
 * @package Psy\TabCompletion\Matchers
 */
class CommandsMatcher extends AbstractMatcher
{
    /** @var array  */
    protected $commands = array();

    public function __construct(array $commands)
    {
        $this->setCommands($commands);
    }

    /**
     * @param Command[] $commands
     */
    public function setCommands(array $commands)
    {
        $names = array();
        foreach ($commands as $command) {
            $names = array_merge(array($command->getName()), $names);
            $names = array_merge($command->getAliases(), $names);
        }
        $this->commands = $names;
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

    /**
     * @param  array $tokens
     * @return bool
     */
    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        switch (true) {
            case self::tokenIs($prevToken, self::T_NEW):
                return false;
            case self::hasToken(array(self::T_OPEN_TAG, self::T_STRING), $token):
                return true;
        }

        return false;
    }
}
