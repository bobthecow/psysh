<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\StaticOperatorRuler;
use Psy\TabCompletion\Rulers\AbstractRuler;

/**
 * Class ClassAttributesMatcher
 * @package Psy\TabCompletion\Matchers
 */
class ClassAttributesMatcher extends AbstractMatcher
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
        $vars = array_merge(
            array_map(
                function ($var) {
                    return '$' . $var;
                },
                array_keys($reflection->getStaticProperties())
            ),
            array_keys($reflection->getConstants())
        );

        return array_map(
            function ($name) use ($class) {
                return $class . '::' . $name;
            },
            array_filter(
                $vars,
                function ($var) use ($input) {
                    return AbstractMatcher::startsWith($input, $var);
                }
            )
        );
    }
}
