<?php

namespace Psy\TabCompletion;

/**
 * Class ClassMethodsMatcher
 * @package Psy\TabCompletion
 */
class ClassMethodsMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        $scope = $this->getScope();
        $reflection = new \ReflectionClass($scope);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);

        return array_filter(array_keys($methods), function ($method) use ($input) {
            AbstractMatcher::startsWith($input, $method);
        });
    }
}
