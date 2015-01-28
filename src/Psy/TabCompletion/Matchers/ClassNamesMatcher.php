<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\ClassNamesRuler;

/**
 * Class ClassNamesMatcher
 * @package Psy\TabCompletion\Matchers
 */
class ClassNamesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    protected function buildRules()
    {
        $this->rules[] = new ClassNamesRuler();
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $class = $this->getInput($tokens);

        return array_filter(
            get_declared_classes(),
            function ($className) use ($class) {
                return AbstractMatcher::startsWith($class, $className);
            }
        );
    }
}
