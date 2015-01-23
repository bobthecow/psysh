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
    /** operators constants */
    const ARROW_OPERATOR = '->';
    const DOUBLE_SEMICOLON_OPERATOR = '::';
    const NEW_OPERATOR = 'new ';
    const DOLLAR_OPERATOR = '$';
    /** matchers constants */
    const COMMANDS_MATCHER = 'commands';
    const KEYWORDS_MATCHER = 'keyword';
    const VARIABLES_MATCHER = 'variables';
    const FUNCTIONS_MATCHER = 'functions';
    const CLASS_NAMES_MATCHER = 'class_names';
    const CLASS_ATTRIBUTES_MATCHER = 'class_attributes';
    const CLASS_METHODS_MATCHER = 'class_methods';
    const OBJECT_ATTRIBUTES_MATCHER = 'object_attributes';
    const OBJECT_METHODS_MATCHER = 'object_methods';

    /** @var Context */
    protected $context;

    /** @var array  */
    protected $matchers;

    /** @var array  */
    protected $operators = array(
        self::ARROW_OPERATOR,
        self::DOUBLE_SEMICOLON_OPERATOR,
        self::NEW_OPERATOR,
        self::DOLLAR_OPERATOR
    );

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
            self::KEYWORDS_MATCHER => new KeywordsMatcher($this->context),
            self::VARIABLES_MATCHER => new VariablesMatcher($this->context),
            self::FUNCTIONS_MATCHER => new FunctionsMatcher($this->context),
            self::CLASS_NAMES_MATCHER => new ClassNamesMatcher($this->context),
            self::CLASS_METHODS_MATCHER => new ClassMethodsMatcher($this->context),
            self::CLASS_ATTRIBUTES_MATCHER => new ClassAttributesMatcher($this->context),
            self::OBJECT_METHODS_MATCHER => new ObjectMethodsMatcher($this->context),
            self::OBJECT_ATTRIBUTES_MATCHER => new ObjectAttributesMatcher($this->context)
        );
    }

    /**
     * @param $key
     * @return AbstractMatcher
     */
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
     * @param $line
     * @return mixed|null
     */
    protected function extractOperatorData($line)
    {
        $copy = $line;
        while (($len = strlen($line)) > 0) {
            foreach ($this->operators as $operator) {
                $opLen = strlen($operator);
                if ($operator === substr($line, $len - $opLen, $opLen)) {
                    preg_match('#(?P<obj>[a-z0-9-_]+)::#im', $line, $matches);
                    if (array_key_exists('obj', $matches)) {
                        return array($operator, $matches['obj'], str_replace($line, '', $copy));
                    }
                    return array($operator, '');
                }
            }
            $line = substr($line, 0, $len - 1);
        }

        return null;
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

        // do we have any operator there near the input of the cursor?
        list($operator, $class, $start) = $this->extractOperatorData($line);
        if (!is_null($operator)) {
            $matches = $this->getMatchesByOperator($operator, $class, $start, $index, $info);

            if (!empty($matches)) {
                return $matches;
            }

            return array('');
        }

        // is it a keyword, command or a function?
        $matches = call_user_func_array('array_merge', array_map(
            function (AbstractMatcher $matcher) use ($input, $index, $info) {
                return $matcher->getMatches($input, $index, $info);
            },
            array(
                $this->getMatcher(self::KEYWORDS_MATCHER),
                $this->getMatcher(self::FUNCTIONS_MATCHER),
                $this->getMatcher(self::COMMANDS_MATCHER),
            )
        ));

        if (!empty($matches)) {
            return $matches;
        }

        return array('');
    }

    protected function getMatchesByOperator($operator, $class, $input, $index, $info = array())
    {
        if (!in_array($operator, $this->operators)) {
            throw new \InvalidArgumentException("Unknown operator: '{$operator}'.");
        }

        /** @var AbstractMatcher[] $matchers */
        $matchers = array();
        $obj = null;
        switch ($operator) {
            case self::DOLLAR_OPERATOR:
                // return variables matched
                $matchers[] = $this->getMatcher(self::VARIABLES_MATCHER);
                break;
            case self::NEW_OPERATOR:
                // return the classes declared in the context
                $matchers[] = $this->getMatcher(self::CLASS_NAMES_MATCHER);
                break;
            case self::ARROW_OPERATOR:
                // dynamic properties from the object
                $matchers[] = $this->getMatcher(self::OBJECT_ATTRIBUTES_MATCHER);
                $matchers[] = $this->getMatcher(self::OBJECT_METHODS_MATCHER);
                $class = $this->context->get($class);
                break;
            case self::DOUBLE_SEMICOLON_OPERATOR:
                $matchers[] = $this->getMatcher(self::CLASS_ATTRIBUTES_MATCHER);
                $matchers[] = $this->getMatcher(self::CLASS_METHODS_MATCHER);
                break;
            default:
                throw new \RuntimeException("Unknown operator received: '{$operator}'");
        }

        return call_user_func_array('array_merge', array_map(
            function (AbstractMatcher $matcher) use ($input, $index, $info, $class) {
                $matcher->setScope($class);
                return $matcher->getMatches($input, $index, $info);
            },
            $matchers
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
