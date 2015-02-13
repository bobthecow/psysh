<?php

namespace Psy\TabCompletion\Matchers;

use Psy\Context;
use Psy\TabCompletion\Rulers\AbstractRuler;

/**
 * Class AbstractMatcher
 * @package Psy\TabCompletion\Matchers
 */
abstract class AbstractMatcher
{
    /** @var Context  */
    protected $context;

    /** @var AbstractRuler[] */
    protected $rules = array();

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->buildRules();
    }

    /**
     * Rules to apply to the tokens for successfully returning the matches
     * @return mixed
     */
    abstract protected function buildRules();

    /**
     * Rule to accomplish for successfully applying the matches for the input
     * @param  array $tokens
     * @return mixed
     */
    public function checkRules(array $tokens)
    {
        foreach ($this->rules as $rule) {
            if ($rule->check($tokens)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  $tokens
     * @return string
     */
    protected function getInput(array $tokens)
    {
        $var = '';
        $firstToken = array_pop($tokens);
        if (AbstractRuler::tokenIs($firstToken, AbstractRuler::T_STRING)) {
            $var = $firstToken[1];
        }

        return $var;
    }

    /**
     * @param $tokens
     * @return string
     */
    protected function getNamespaceAndClass($tokens)
    {
        $class = '';
        while (AbstractRuler::hasToken(
            array(AbstractRuler::T_NS_SEPARATOR, AbstractRuler::T_STRING),
            $token = array_pop($tokens)
        )) {
            $class = $token[1] . $class;
        }

        return $class;
    }

    /**
     * @param  array $tokens information substracted with get_token_all
     * @param  array $info   readline_info object
     * @return array The matches resulting from the query
     */
    abstract public function getMatches(array $tokens, array $info = array());

    /**
     * @param $prefix
     * @param $word
     * @return int
     */
    public static function startsWith($prefix, $word)
    {
        return preg_match(sprintf('#^%s#', $prefix), $word);
    }

    /**
     * @param $var
     * @return mixed
     */
    protected function getVariable($var)
    {
        return $this->context->get($var);
    }
}
