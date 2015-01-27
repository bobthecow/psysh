<?php

namespace Psy\TabCompletion;

/**
 * Class ClassAttributesMatcher
 * @package Psy\TabCompletion
 */
class ClassAttributesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        $scope = $this->getScope();
        $reflection = new \ReflectionClass($scope);
        $vars = array_merge(
            array_map(
                function ($var) {
                    return '$' . $var;
                }, array_keys($reflection->getStaticProperties())
            ),
            array_keys($reflection->getConstants())
        );

        return array_map(
            function ($name) use ($scope) {
                return $scope . AutoCompleter::DOUBLE_SEMICOLON_OPERATOR . $name;
            },
            array_filter(
                $vars,
                function ($var) use ($input) {
                    return AbstractMatcher::startsWith($input, $var);
                }
            )
        );
    }
}
