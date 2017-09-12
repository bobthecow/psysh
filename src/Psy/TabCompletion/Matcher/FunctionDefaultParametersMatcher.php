<?php

namespace Psy\TabCompletion\Matcher;

class FunctionDefaultParametersMatcher extends AbstractDefaultParametersMatcher
{
    public function getMatches(array $tokens, array $info = array())
    {
        array_pop($tokens); // open bracket

        $functionName = array_pop($tokens);

        try {
            $reflection = new \ReflectionFunction($functionName[1]);
        } catch (\ReflectionException $e) {
            return array();
        }

        $parameters = $reflection->getParameters();

        return $this->getDefaultParameterCompletion($parameters);
    }

    public function hasMatched(array $tokens)
    {
        $openBracket = array_pop($tokens);

        if ($openBracket !== '(') {
            return false;
        }

        $functionName = array_pop($tokens);

        if (!self::tokenIs($functionName, self::T_STRING)) {
            return false;
        }

        if (!function_exists($functionName[1])) {
            return false;
        }

        return true;
    }
}
