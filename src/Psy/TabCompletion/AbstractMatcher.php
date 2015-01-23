<?php

namespace Psy\TabCompletion;

use Psy\Context;

/**
 * Class AbstractMatcher
 * @package Psy\TabCompletion
 */
abstract class AbstractMatcher
{
    protected $context;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param  $input input received from the readline completion
     * @param  $index cursor position when the readline completion occured
     * @param  array $info readline_info object
     * @return array The matches resulting from the query
     */
    abstract public function getMatches($input, $index, $info = array());

    /**
     * @param $prefix
     * @param $word
     * @return int
     */
    protected function startsWith($prefix, $word)
    {
        return preg_match(sprintf('#^%s#', $prefix), $word);
    }

    /**
     * @param $var
     * @return mixed
     */
    protected function getVariable($var)
    {
        return $this->context->get($var);
    }
}
