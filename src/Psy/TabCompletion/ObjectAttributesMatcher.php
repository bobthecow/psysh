<?php

namespace Psy\TabCompletion;

/**
 * Class ObjectAttributesMatcher
 * @package Psy\TabCompletion
 */
class ObjectAttributesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        $scope = $this->getScope();
        return array_filter(array_keys(get_class_vars(get_class($scope))), function ($var) use ($input, $index, $info) {
            return AbstractMatcher::startsWith($input, $var);
        });
    }
}
