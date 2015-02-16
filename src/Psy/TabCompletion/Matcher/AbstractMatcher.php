<?php

namespace Psy\TabCompletion\Matcher;

/**
 * Class AbstractMatcher
 * @package Psy\TabCompletion\Matcher
 */
abstract class AbstractMatcher
{
    /** Syntax types */
    const CONSTANT_SYNTAX = '^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$';
    const VAR_SYNTAX = '^\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$';
    const MISC_OPERATORS = '+-*/^|&';
    /** Token values */
    const T_OPEN_TAG = 'T_OPEN_TAG';
    const T_VARIABLE = 'T_VARIABLE';
    const T_OBJECT_OPERATOR = 'T_OBJECT_OPERATOR';
    const T_DOUBLE_COLON = 'T_DOUBLE_COLON';
    const T_NEW = 'T_NEW';
    const T_CLONE = 'T_CLONE';
    const T_NS_SEPARATOR = 'T_NS_SEPARATOR';
    const T_STRING = 'T_STRING';
    const T_WHITESPACE = 'T_WHITESPACE';
    const T_AND_EQUAL = 'T_AND_EQUAL';
    const T_BOOLEAN_AND = 'T_BOOLEAN_AND';
    const T_BOOLEAN_OR = 'T_BOOLEAN_OR';

    /**
     * @param  array $tokens
     * @return bool
     */
    public function hasMatched(array $tokens)
    {
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
        if (self::tokenIs($firstToken, self::T_STRING)) {
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
        while (self::hasToken(
            array(self::T_NS_SEPARATOR, self::T_STRING),
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
     * @param $token
     * @param  string $syntax
     * @return bool
     */
    public static function hasSyntax($token, $syntax = self::VAR_SYNTAX)
    {
        if (!is_array($token)) {
            return false;
        }

        $regexp = sprintf('#%s#', $syntax);

        return (bool) preg_match($regexp, $token[1]);
    }

    /**
     * @param $token
     * @param $which
     * @return bool
     */
    public static function tokenIs($token, $which)
    {
        if (!is_array($token)) {
            return false;
        }

        return token_name($token[0]) === $which;
    }

    /**
     * @param $token
     * @return bool
     */
    public static function isOperator($token)
    {
        if (!is_string($token)) {
            return false;
        }

        return strpos(self::MISC_OPERATORS, $token) !== false;
    }

    /**
     * @param  array $coll
     * @param $token
     * @return bool
     */
    public static function hasToken(array $coll, $token)
    {
        if (!is_array($token)) {
            return false;
        }

        return in_array(token_name($token[0]), $coll);
    }
}
