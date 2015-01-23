<?php

namespace Psy\TabCompletion;

use Psy\Context;

/**
 * Class VariableMatcher
 * @package Psy\TabCompletion
 */
class VariableMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        return array_filter($this->context->getAll(), function ($variable) use ($input) {
            return $this->startsWith($variable, $input);
        });
    }
}
