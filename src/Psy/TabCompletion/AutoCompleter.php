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
    /** matchers constants */
    const COMMANDS_MATCHER = 'commands';
    const KEYWORDS_MATCHER = 'keyword';
    const VARIABLES_MATCHER = 'variables';
    const CONSTANTS_MATCHER = 'constants';
    const FUNCTIONS_MATCHER = 'functions';
    const CLASS_NAMES_MATCHER = 'class_names';
    const CLASS_ATTRIBUTES_MATCHER = 'class_attributes';
    const CLASS_METHODS_MATCHER = 'class_methods';
    const OBJECT_ATTRIBUTES_MATCHER = 'object_attributes';
    const OBJECT_METHODS_MATCHER = 'object_methods';

    /** @var Context */
    protected $context;

    /** @var Matchers\AbstractMatcher[]  */
    public $matchers;

    /**
     * @param Context $context
     * @param array   $commands
     */
    public function __construct(Context $context = null, array $commands = array())
    {
        $this->context = $context;
        $this->registerMatchers();
        $this->setCommands($commands);
    }

    /**
     *
     */
    protected function registerMatchers()
    {
        $this->matchers = array(
            self::COMMANDS_MATCHER => new Matchers\CommandsMatcher($this->context),
            self::KEYWORDS_MATCHER => new Matchers\KeywordsMatcher($this->context),
            self::VARIABLES_MATCHER => new Matchers\VariablesMatcher($this->context),
            self::CONSTANTS_MATCHER => new Matchers\ConstantsMatcher($this->context),
            self::FUNCTIONS_MATCHER => new Matchers\FunctionsMatcher($this->context),
            self::CLASS_NAMES_MATCHER => new Matchers\ClassNamesMatcher($this->context),
            self::CLASS_METHODS_MATCHER => new Matchers\ClassMethodsMatcher($this->context),
            self::CLASS_ATTRIBUTES_MATCHER => new Matchers\ClassAttributesMatcher($this->context),
            self::OBJECT_METHODS_MATCHER => new Matchers\ObjectMethodsMatcher($this->context),
            self::OBJECT_ATTRIBUTES_MATCHER => new Matchers\ObjectAttributesMatcher($this->context),
        );
    }

    /**
     * @param $commands
     */
    public function setCommands(array $commands)
    {
        /** @var Matchers\CommandsMatcher $commandsMatcher */
        $commandsMatcher = $this->matchers[self::COMMANDS_MATCHER];
        $commandsMatcher->setCommands(
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
        $tokens = token_get_all('<?php ' . $line);

        $matches = array();
        foreach ($this->matchers as $matcher) {
            if ($matcher->checkRules($tokens)) {
                $matches = array_merge($matcher->getMatches($tokens), $matches);
            }
        }

        return !empty($matches) ? $matches : array('');
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
