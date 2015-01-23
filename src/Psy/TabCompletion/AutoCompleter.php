<?php

namespace Psy\TabCompletion;

use Psy\Command\Command;
use Psy\Context;

/**
 * Class AutoCompleter
 * @package Psy\TabCompletion
 */
class AutoCompleter
{
    /** constants */
    const COMMANDS_MATCHER = 'commands';
    const KEYWORDS_MATCHER = 'keyword';
    const VARIABLES_MATCHER = 'variables';
    const FUNCTIONS_MATCHER = 'functions';
    const CLASS_ATTRIBUTES_MATCHER = 'class_attributes';
    const CLASS_METHODS_MATCHER = 'class_methods';

    /** @var Context */
    protected $context;

    /** @var array  */
    protected $matchers;

    /**
     * @param Context $context
     */
    public function __construct(Context $context = null)
    {
        $this->context = $context;
        $this->registerMatchers();
    }

    protected function registerMatchers()
    {
        $this->matchers = array(
            self::COMMANDS_MATCHER => new CommandsMatcher($this->context),
            self::KEYWORDS_MATCHER => new KeywordMatcher($this->context),
            self::VARIABLES_MATCHER => new VariableMatcher($this->context),
            self::FUNCTIONS_MATCHER => new FunctionsMatcher($this->context),
            self::CLASS_METHODS_MATCHER => new ClassMethodMatcher($this->context),
            self::CLASS_ATTRIBUTES_MATCHER => new ClassAttributesMatcher($this->context)
        );
    }

    protected function getMatcher($key)
    {
        if (!in_array($key, array_keys($this->matchers))) {
            throw new \InvalidArgumentException("The key '{$key}' is not found in the matchers registered.");
        }

        return $this->matchers[$key];
    }

    public function setCommands($commands)
    {
        $this->getMatcher(self::COMMANDS_MATCHER)->setCommands(
            array_map(function (Command $command) {
                return $command->getName();
            }, $commands)
        );
    }

    public function activate()
    {
        readline_completion_function(array(&$this, 'callback'));
    }

    /**
     * @param string $input Readline current word
     * @param int    $index Current word index
     * @param array  $info  readline_info() data
     *
     * @return array
     */
    public function processCallback($input, $index, $info = array())
    {
        $line = substr($info['line_buffer'], 0, $info['end']);

        // the char just before the current word is a dollar? send a variable context
        $charAt = substr($line, $index - 1, 1);
        if ($charAt === '$') {
            return array_keys($this->context->getAll());
        }

        $parenthesize = function ($name) {
            return sprintf('%s()', $name);
        };

        if (strlen($line) > 4) {
            // if the current position of the cursor has a precending new keyword send the classes names
            if (preg_match('#\bnew\s+(\\\\\w*)*$#', substr($line, 0, $index))) {
                return array_map($parenthesize, array_filter(get_declared_classes(), function ($class) use ($input) {
                    return preg_match(sprintf('#^%s#', $input), $class);
                }));
            }
        }

        // is it a keyword, command or a function?
        return call_user_func_array('array_merge', array_map(
            function (AbstractMatcher $matcher) use ($input, $index, $info) {
                return $matcher->getMatches($input, $index, $info);
            },
            array(
                $this->getMatcher(self::KEYWORDS_MATCHER),
                $this->getMatcher(self::FUNCTIONS_MATCHER),
                $this->getMatcher(self::COMMANDS_MATCHER),
            )
        ));
    }

    /**
     * @param $input
     * @param $index
     * @return array
     */
    public function callback($input, $index)
    {
        return $this->processCallback($input, $index, readline_info());
    }
}
