<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\AbstractRuler;
use Psy\TabCompletion\Rulers\ObjectOperatorRuler;

/**
 * Class ObjectAttributesMatcher
 * @package Psy\TabCompletion\Matchers
 */
class ObjectAttributesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    protected function buildRules()
    {
        $this->rules[] = new ObjectOperatorRuler();
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);

        $firstToken = array_pop($tokens);
        if (AbstractRuler::tokenIs($firstToken, AbstractRuler::T_STRING)) {
            // second token is the object operator
            array_pop($tokens);
        }
        $objectToken = array_pop($tokens);
        $objectName = str_replace('$', '', $objectToken[1]);
        $object = $this->context->get($objectName);

        return array_filter(
            array_keys(get_class_vars(get_class($object))),
            function ($var) use ($input) {
                return AbstractMatcher::startsWith($input, $var);
            }
        );
    }
}
