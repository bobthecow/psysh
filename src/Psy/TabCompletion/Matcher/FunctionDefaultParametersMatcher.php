<?php

namespace Psy\TabCompletion\Matcher;

class FunctionDefaultParametersMatcher extends AbstractMatcher
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

        $parametersProcessed = array();

        foreach ($parameters as $parameter) {
            if (!$parameter->isDefaultValueAvailable()) {
                return array();
            }

            $defaultValue = $parameter->getDefaultValue();
            if (is_string($defaultValue)) {
                $defaultValue = "'{$defaultValue}'";
            }

            $parametersProcessed[] = "\${$parameter->getName()} = $defaultValue";
        }

        if (empty($parametersProcessed)) {
            return array();
        }

        return array(implode(',', $parametersProcessed) . ')');
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
