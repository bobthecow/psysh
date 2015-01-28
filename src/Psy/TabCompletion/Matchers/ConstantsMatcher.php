<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\ConstantsRuler;

/**
 * Class ConstantsMatcher
 * @package Psy\TabCompletion\Matchers
 */
class ConstantsMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    protected function buildRules()
    {
        $this->rules[] = new ConstantsRuler();
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $const = $this->getInput($tokens);

        return array_filter(array_keys(get_defined_constants()), function ($constant) use ($const) {
            return AbstractMatcher::startsWith($const, $constant);
        });
    }
}
