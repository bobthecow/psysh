<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\TabCompletion\Matcher;

use Psy\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * A Psy Command tab completion Matcher.
 *
 * This matcher provides completion for all registered Psy Command names and
 * aliases.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class CommandsMatcher extends AbstractMatcher
{
    /** @var string[] */
    protected $commandNames = array();

    /** @var  Command[] */
    protected $commands = array();

    /**
     * CommandsMatcher constructor.
     *
     * @param Command[] $commands
     */
    public function __construct(array $commands)
    {
        $this->setCommands($commands);
    }

    /**
     * Set Commands for completion.
     *
     * @param Command[] $commands
     */
    public function setCommands(array $commands)
    {
        // use the same object so is leaks the lowest memory possible
        foreach ($commands as &$command) {
            $this->commands[$command->getName()] = &$command;
            foreach ($command->getAliases() as $alias) {
                $this->commands[$alias] = &$command;
            }
            $this->commandNames = array_keys($this->commands);
        }
    }

    /**
     * @param $name
     *
     * @return bool
     */
    protected function isCommand($name)
    {
        return in_array($name, $this->commandNames);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    protected function matchCommand($name)
    {
        foreach ($this->commandNames as $cmd) {
            if ($this->startsWith($name, $cmd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);
        $prevToken = array_pop($tokens);
        if (self::tokenIs($prevToken, self::T_STRING) && $prevToken[1] === $input) {
            $prevToken = array_pop($tokens);
        }

        if (is_string($prevToken) && $prevToken === '-') {
            // user is asking for the command parameters
            // php open tag
            array_shift($tokens);
            // the command
            $commandToken = array_shift($tokens);
            $commandName = $commandToken[1];
            $command = &$this->commands[$commandName];

            $options = $command->getDefinition()->getOptions();
            $shorts = array_filter(array_map(function (InputOption $option) {
                if ($shortcut = $option->getShortcut()) {
                    return $shortcut;
                }
            }, $options));

            $matches = array_filter(
                array_values($shorts),
                function ($short) use ($input) {
                    return AbstractMatcher::startsWith($input, $short);
                }
            );

            if (! empty($matches)) {
                return array_map(
                    function ($opt) {
                        return '-' . $opt;
                    },
                    $matches
                );
            }
        }

        return array_filter($this->commandNames, function ($command) use ($input) {
            return AbstractMatcher::startsWith($input, $command);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function hasMatched(array $tokens)
    {
        /* $openTag */ array_shift($tokens);
        $command = array_shift($tokens);
        $dash = null;
        if (count($tokens) > 0) {
            $dash = array_shift($tokens);
        }

        switch (true) {
            case self::tokenIs($command, self::T_STRING) &&
                !$this->isCommand($command[1]) &&
                $this->matchCommand($command[1]) &&
                empty($tokens):
            case self::tokenIs($command, self::T_STRING) &&
                !$this->isCommand($command[1]) &&
                $this->matchCommand($command[1]) &&
                $dash === '-':
                return true;
        }

        return false;
    }
}
