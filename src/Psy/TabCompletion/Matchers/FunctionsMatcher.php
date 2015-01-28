<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\ClassNamesRuler;

/**
 * Class FunctionsMatcher
 * @package Psy\TabCompletion\Matchers
 */
class FunctionsMatcher extends AbstractMatcher
{
    protected function buildRules()
    {
        $this->rules[] = new ClassNamesRuler();
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $func = $this->getInput($tokens);

        $functions = get_defined_functions();
        $allFunctions = array_merge($functions['user'], $functions['internal']);

        return array_filter($allFunctions, function ($function) use ($func) {
            return AbstractMatcher::startsWith($func, $function);
        });
    }
}
