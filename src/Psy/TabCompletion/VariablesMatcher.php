<?php

namespace Psy\TabCompletion;

/**
 * Class VariablesMatcher
 * @package Psy\TabCompletion
 */
class VariablesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        return array_filter(array_keys($this->context->getAll()), function ($variable) use ($input) {
            return AbstractMatcher::startsWith($input, $variable);
        });
    }
}
