<?php

namespace Psy\TabCompletion\Matcher;


class ObjectMethodDefaultParametersMatcher extends AbstractContextAwareMatcher
{
    public function getMatches(array $tokens, array $info = array())
    {
        $openBracket = array_pop($tokens);
        $functionName = array_pop($tokens);
        $methodOperator = array_pop($tokens);

        $objectToken = array_pop($tokens);
        if (!is_array($objectToken)) {
            return array();
        }

        $objectName = str_replace('$', '', $objectToken[1]);

        try {
            $object = $this->getVariable($objectName);
            $reflection = new \ReflectionObject($object);
        } catch (InvalidArgumentException $e) {
            return array();
        } catch (\ReflectionException $e) {
            return array();
        }

        $methods = $reflection->getMethods();

        foreach($methods as $method) {
            if ($method->getName() === $functionName[1]) {
                $parameterString = $this->extractParameterString($method);

                if (empty($parameterString)) {
                    return [];
                }

                return [$parameterString];
            }
        }

        return [];
    }

    /**
     * @param \ReflectionMethod $method
     * @return string
     */
    private function extractParameterString($method)
    {
        $parameters = $method->getParameters();

        $parametersProcessed = [];

        foreach($parameters as $parameter) {
            if (!$parameter->isDefaultValueAvailable()) {
                return '';
            }

            $defaultValue = $parameter->getDefaultValue();
            if (is_string($defaultValue)) {
                $defaultValue = "'{$defaultValue}'";
            }

            $parametersProcessed[] = "\${$parameter->getName()} = {$defaultValue}";
        }

        return implode(',', $parametersProcessed) . ')';
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

        $operator = array_pop($tokens);

        if (!self::tokenIs($operator, self::T_OBJECT_OPERATOR)) {
            return false;
        }

        return true;
    }
}