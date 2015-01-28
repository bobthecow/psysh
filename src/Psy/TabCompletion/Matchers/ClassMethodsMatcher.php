<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\AbstractRuler;
use Psy\TabCompletion\Rulers\StaticOperatorRuler;

/**
 * Class ClassMethodsMatcher
 * @package Psy\TabCompletion\Matchers
 */
class ClassMethodsMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    protected function buildRules()
    {
        $this->rules[] = new StaticOperatorRuler();
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);

        $firstToken = array_pop($tokens);
        if (AbstractRuler::tokenIs($firstToken, AbstractRuler::T_STRING)) {
            // second token is the nekudotayim operator
            array_pop($tokens);
        }

        $class = $this->getNamespaceAndClass($tokens);

        $reflection = new \ReflectionClass($class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);
        $methods = array_map(function (\ReflectionMethod $method) {
            return $method->getName();
        }, $methods);

        return array_map(
            function ($name) use ($class) {
                return $class . '::' . $name;
            },
            array_filter($methods, function ($method) use ($input) {
                return AbstractMatcher::startsWith($input, $method);
            })
        );
    }
}
