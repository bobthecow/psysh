<?php

namespace Psy\TabCompletion;

/**
 * Class ObjectMethodsMatcher
 * @package Psy\TabCompletion
 */
class ObjectMethodsMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        $scope = $this->getScope();
        return array_filter(get_class_methods($scope), function ($var) use ($input, $index, $info) {
            return AbstractMatcher::startsWith($input, $var);
        });
    }
}
