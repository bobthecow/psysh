<?php

namespace Psy\TabCompletion\Matcher;

class ClassMethodDefaultParametersMatcher extends AbstractMatcher
{
    public function getMatches(array $tokens, array $info = array())
    {
        $openBracket = array_pop($tokens);
        $functionName = array_pop($tokens);
        $methodOperator = array_pop($tokens);

        $class = $this->getNamespaceAndClass($tokens);

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            // In this case the class apparently does not exist, so we can do nothing
            return array();
        }

        $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);

        foreach ($methods as $method) {
            if ($method->getName() === $functionName[1]) {
                $parameterString = $this->extractParameterString($method);

                if (empty($parameterString)) {
                    return array();
                }

                return array($parameterString);
            }
        }

        return array();
    }

    /**
     * @param \ReflectionMethod $method
     *
     * @return string
     */
    private function extractParameterString(\ReflectionMethod $method)
    {
        $parameters = $method->getParameters();

        $parametersProcessed = array();

        foreach ($parameters as $parameter) {
            if (!$parameter->isDefaultValueAvailable()) {
                return '';
            }

            $defaultValue = var_export($parameter->getDefaultValue(), true);

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

        if (!self::tokenIs($operator, self::T_DOUBLE_COLON)) {
            return false;
        }

        return true;
    }
}
