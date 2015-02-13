<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\ClassNamesRuler;
use Psy\Util\Mirror;

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
        $class = $this->getNamespaceAndClass($tokens);
        if ($class[0] === "\\") {
            $class = substr($class, 1, strlen($class));
        }
        $quotedClass = preg_quote($class);

        return array_map(
            function ($className) use ($class) {
                // get the number of namespace separators
                $nsPos = substr_count($class, '\\');
                $pieces = explode('\\', $className);
                $methods = Mirror::get($class);
                return $pieces[$nsPos];
            },
            array_filter(
                get_declared_classes(),
                function ($className) use ($quotedClass) {
                    return AbstractMatcher::startsWith($quotedClass, $className);
                }
            )
        );
    }
}
