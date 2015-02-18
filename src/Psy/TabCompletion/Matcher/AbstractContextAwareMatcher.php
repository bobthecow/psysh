<?php

namespace Psy\TabCompletion\Matcher;

use Psy\Context;
use Psy\ContextAware;

/**
 * An abstract tab completion Matcher which implements ContextAware.
 *
 * The AutoCompleter service will inject a Context instance into all
 * ContextAware Matchers.
 */
abstract class AbstractContextAwareMatcher extends AbstractMatcher implements ContextAware
{
    /**
     * Context instance (for ContextAware interface)
     *
     * @type Context
     */
    protected $context;

    /**
     * ContextAware interface.
     *
     * @param Context $context
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Get a Context variable by name.
     *
     * @param $var Variable name
     *
     * @return mixed
     */
    protected function getVariable($var)
    {
        return $this->context->get($var);
    }

    /**
     * Get all variables in the current Context.
     *
     * @return array
     */
    protected function getVariables()
    {
        return $this->context->getAll();
    }
}
