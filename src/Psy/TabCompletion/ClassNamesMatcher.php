<?php

namespace Psy\TabCompletion;

/**
 * Class ClassNamesMatcher
 * @package Psy\TabCompletion
 */
class ClassNamesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        return array_filter(
            get_declared_classes(),
            function ($className) use ($input, $index, $info) {
                return AbstractMatcher::startsWith($input, $className);
            }
        );
    }
}
