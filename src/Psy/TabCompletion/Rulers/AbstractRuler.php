<?php

namespace Psy\TabCompletion\Rulers;

/**
 * Class AbstractRuler
 * @package Psy\TabCompletion\Rulers
 */
class AbstractRuler
{
    /** Syntax types */
    const CONSTANT_SYNTAX = '^[A-Z0-9-_]+$';
    const VAR_SYNTAX = '^\$[a-zA-Z0-9-_]+$';
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

    protected $allowedStartTokens = array(
        'T_WHITESPACE', 'T_OPEN_TAG', 'T_AND_EQUAL', 'T_CLONE',
    );

    /**
     * @param $tokens
     * @return bool
     */
    public function check($tokens)
    {
        $token = array_pop($tokens);

        return $token[0] === T_OPEN_TAG;
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
