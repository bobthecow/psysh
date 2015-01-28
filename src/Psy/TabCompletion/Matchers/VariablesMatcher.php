<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\VariableRuler;

/**
 * Class VariablesMatcher
 * @package Psy\TabCompletion\Matchers
 */
class VariablesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    protected function buildRules()
    {
        $this->rules[] = new VariableRuler();
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $var = str_replace('$', '', $this->getInput($tokens));

        return array_filter(array_keys($this->context->getAll()), function ($variable) use ($var) {
            return AbstractMatcher::startsWith($var, $variable);
        });
    }
}
