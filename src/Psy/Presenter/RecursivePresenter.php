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
 * An abstract Presenter capable of recursively presenting sub-values.
 */
abstract class RecursivePresenter implements Presenter, PresenterManagerAware
{
    const MAX_DEPTH = 5;
    const INDENT    = '    ';

    protected $manager;
    protected $depth;

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
     * Present the recursive value.
     *
     * Subclasses should implement `presentValue` rather than overriding this
     * method.
     *
     * @see self::presentValue()
     *
     * @param mixed $value
     * @param int   $depth   (default: null)
     * @param int   $options One of Presenter constants
     *
     * @return string
     */
    public function present($value, $depth = null, $options = 0)
    {
        $this->setDepth($depth);

        return $this->presentValue($value, $depth, $options);
    }

    /**
     * RecursivePresenter subclasses implement a `presentValue` method for
     * actually doing the presentation.
     *
     * @param mixed $value
     *
     * @return string
     */
    abstract protected function presentValue($value);

    /**
     * Keep track of the remaining recursion depth.
     *
     * If $depth is null, set it to `self::MAX_DEPTH`.
     *
     * @param int $depth (default: null)
     */
    protected function setDepth($depth = null)
    {
        $this->depth = $depth === null ? self::MAX_DEPTH : $depth;
    }

    /**
     * Present a sub-value.
     *
     * If the current recursion depth is greater than self::MAX_DEPTH, it will
     * present a reference, otherwise it will present the full representation
     * of the sub-value.
     *
     * @see PresenterManager::present()
     * @see PresenterManager::presentRef()
     *
     * @param mixed $value
     * @param int   $options One of Presenter constants
     *
     * @return string
     */
    protected function presentSubValue($value, $options = 0)
    {
        $depth = $this->depth;
        if ($depth > 0) {
            $formatted = $this->manager->present($value, $depth - 1, $options);
            $this->setDepth($depth);

            return $formatted;
        } else {
            return $this->manager->presentRef($value);
        }
    }

    /**
     * Indent every line of a value.
     *
     * @param string $value
     *
     * @return string
     */
    protected function indentValue($value)
    {
        return str_replace(PHP_EOL, PHP_EOL . self::INDENT, $value);
    }
}
