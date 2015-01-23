<?php

namespace Psy\TabCompletion;

use Psy\Context;

/**
 * Class AbstractMatcher
 * @package Psy\TabCompletion
 */
abstract class AbstractMatcher
{
    /** @var Context  */
    protected $context;

    protected $scope;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param  $input string input received from the readline completion
     * @param  $index int cursor position when the readline completion occured
     * @param  array $info readline_info object
     * @return array The matches resulting from the query
     */
    abstract public function getMatches($input, $index, $info = array());

    /**
     * @param $prefix
     * @param $word
     * @return int
     */
    public static function startsWith($prefix, $word)
    {
        return preg_match(sprintf('#^%s#', $prefix), $word);
    }

    /**
     * @param $scope mixed The object to query
     */
    public function setScope($scope)
    {
        $this->scope = $scope;
    }

    /**
     * @return mixed
     */
    public function getScope()
    {
        return $this->scope;
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
