<?php

namespace Psy\TabCompletion\Matcher;

use Psy\Context;
use Psy\ContextAware;

/**
 * Class AbstractContextAwareMatcher
 * @package Psy\TabCompletion\Matcher
 */
abstract class AbstractContextAwareMatcher extends AbstractMatcher implements ContextAware
{
    /** @var Context  */
    protected $context;

    /**
     * @param Context $context
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param $var
     * @return mixed
     */
    protected function getVariable($var)
    {
        return $this->context->get($var);
    }

    /**
     * @return array
     */
    protected function getVariables()
    {
        return $this->context->getAll();
    }
}
