<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Presenter;

/**
 * A Closure Presenter.
 */
class ClosurePresenter implements Presenter, PresenterManagerAware
{
    const FMT     = '<keyword>function</keyword> (%s)%s { <comment>...</comment> }';
    const USE_FMT = ' use (%s)';

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
    public function presentRef($value)
    {
        return sprintf(
            self::FMT,
            $this->formatParams($value),
            $this->formatStaticVariables($value)
        );
    }

    /**
     * Present the Closure.
     *
     * @param \Closure $value
     * @param int      $depth   (default:null)
     * @param int      $options One of Presenter constants
     *
     * @return string
     */
    public function present($value, $depth = null, $options = 0)
    {
        return $this->presentRef($value);
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
        $ret = $this->formatParamName($param->name);

        if ($param->isOptional()) {
            $ret .= ' = ';

            if (version_compare(PHP_VERSION, '5.4.3', '>=') && $param->isDefaultValueConstant()) {
                $name = $param->getDefaultValueConstantName();
                $ret .= '<const>' . $name . '</const>';
            } elseif ($param->isDefaultValueAvailable()) {
                $ret .= $this->manager->presentRef($param->getDefaultValue());
            } else {
                $ret .= '<urgent>?</urgent>';
            }
        }

        return $ret;
    }

    /**
     * Format static (used) variable names.
     *
     * @param \Closure $value
     *
     * @return string
     */
    protected function formatStaticVariables(\Closure $value)
    {
        $r = new \ReflectionFunction($value);
        $used = $r->getStaticVariables();
        if (empty($used)) {
            return '';
        }

        $names = array_map(array($this, 'formatParamName'), array_keys($used));

        return sprintf(
            self::USE_FMT,
            implode(', ', $names)
        );
    }

    /**
     * Format a Closure parameter name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function formatParamName($name)
    {
        return sprintf('$<strong>%s</strong>', $name);
    }
}
