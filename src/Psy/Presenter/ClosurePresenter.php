<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Presenter;

use Psy\Presenter\AbstractPresenter;

/**
 * A Closure Presenter.
 */
class ClosurePresenter extends AbstractPresenter
{
    /**
     * ClosurePresenter can present closures.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return $value instanceof \Closure;
    }

    /**
     * Present the Closure.
     *
     * @param \Closure $value
     * @param int     $depth (default:null)
     *
     * @return string
     */
    public function present($value, $depth = null)
    {
        return sprintf('function(%s) { ... }', $this->formatParams($value));
    }

    /**
     * Format a list of Closure parameters.
     *
     * @param \Closure $value
     *
     * @return string
     */
    protected function formatParams(\Closure $value)
    {
        $r = new \ReflectionFunction($value);
        $params = array_map(array($this, 'formatParam'), $r->getParameters());

        return implode(', ', $params);
    }

    /**
     * Format an individual Closure parameter.
     *
     * @param \ReflectionParameter $param
     *
     * @return string
     */
    protected function formatParam(\ReflectionParameter $param)
    {
        $ret = '$' . $param->name;

        if ($param->isOptional()) {
            $ret .= ' = ';

            if (version_compare(PHP_VERSION, '5.4.3', '>=') && $param->isDefaultValueConstant()) {
                $ret .= $param->getDefaultValueConstantName();
            } elseif($param->isDefaultValueAvailable()) {
                $ret .= json_encode($param->getDefaultValue());
            } else {
                $ret .= '?';
            }
        }

        return $ret;
    }
}
