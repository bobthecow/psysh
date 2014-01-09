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

use Psy\Presenter\Presenter;

/**
 * A Closure Presenter.
 */
class ClosurePresenter implements Presenter, PresenterManagerAware
{
    const COLOR_FMT = '<keyword>function</keyword>(%s) { <comment>...</comment> }';
    const FMT       = 'function (%s) { ... }';

    protected $manager;

    /**
     * PresenterManagerAware interface.
     *
     * @param PresenterManager $manager
     */
    public function setPresenterManager(PresenterManager $manager)
    {
        $this->manager = $manager;
    }

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
     * Present a reference to the value.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function presentRef($value, $color = false)
    {
        $format = $color ? self::COLOR_FMT : self::FMT;

        return sprintf($format, $this->formatParams($value, $color));
    }

    /**
     * Present the Closure.
     *
     * @param \Closure $value
     * @param int      $depth (default:null)
     *
     * @return string
     */
    public function present($value, $depth = null)
    {
        return $this->presentRef($value, false);
    }

    /**
     * Format a list of Closure parameters.
     *
     * @param \Closure $value
     *
     * @return string
     */
    protected function formatParams(\Closure $value, $color = false)
    {
        $r = new \ReflectionFunction($value);
        $method = $color ? 'formatParamColor' : 'formatParam';
        $params = array_map(array($this, $method), $r->getParameters());

        return implode(', ', $params);
    }

    /**
     * PHP's "map" implementation leaves much to be desired.
     *
     * @param \ReflectionParameter $param
     *
     * @return string
     */
    protected function formatParamColor(\ReflectionParameter $param)
    {
        return $this->formatParam($param, true);
    }

    /**
     * Format an individual Closure parameter.
     *
     * @param \ReflectionParameter $param
     *
     * @return string
     */
    protected function formatParam(\ReflectionParameter $param, $color = false)
    {
        $ret = '$' . $param->name;
        if ($color) {
            $ret = '<strong>' . $ret . '</strong>';
        }

        if ($param->isOptional()) {
            $ret .= ' = ';

            if (version_compare(PHP_VERSION, '5.4.3', '>=') && $param->isDefaultValueConstant()) {
                $name = $param->getDefaultValueConstantName();
                if ($color) {
                    $ret .= '<const>'.$name.'</const>';
                } else {
                    $ret .= $name;
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $ret .= $this->manager->presentRef($param->getDefaultValue(), $color);
            } else {
                if ($color) {
                    $ret .= '<urgent>?</urgent>';
                } else {
                    $ret .= '?';
                }
            }
        }

        return $ret;
    }
}
