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
 * A Presenter manager service.
 *
 * Presenters are registered with the PresenterManager, which then delegates
 * value presentation to the most recently registered Presenter capable of
 * presenting that value.
 */
class PresenterManager implements Presenter, \IteratorAggregate
{
    protected $presenters = array();

    /**
     * PresenterManager constructor.
     *
     * Initializes default Presenters.
     */
    public function __construct()
    {
        $this->addPresenters(array(
            new ObjectPresenter(), // lowest precedence
            new ArrayPresenter(),
            new ClosurePresenter(),
            new ExceptionPresenter(),
            new ResourcePresenter(),
            new ScalarPresenter(),
        ));
    }

    /**
     * Register Presenters.
     *
     * Presenters should be passed in an array from lowest to highest precedence.
     *
     * @see self::addPresenter
     *
     * @param Presenter[] $presenters
     */
    public function addPresenters(array $presenters)
    {
        foreach ($presenters as $presenter) {
            $this->addPresenter($presenter);
        }
    }

    /**
     * Register a Presenter.
     *
     * If multiple Presenters are able to present a value, the most recently
     * registered Presenter takes precedence.
     *
     * If $presenter is already registered, it will be re-registered as the
     * highest precedence Presenter.
     *
     * @param Presenter $presenter
     */
    public function addPresenter(Presenter $presenter)
    {
        $this->removePresenter($presenter);

        if ($presenter instanceof PresenterManagerAware) {
            $presenter->setPresenterManager($this);
        }

        array_unshift($this->presenters, $presenter);
    }

    /**
     * Unregister a Presenter.
     *
     * @param Presenter $presenter
     */
    public function removePresenter(Presenter $presenter)
    {
        foreach ($this->presenters as $i => $p) {
            if ($p === $presenter) {
                unset($this->presenters[$i]);
            }
        }
    }

    /**
     * Check whether a Presenter is registered for $value.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return $this->getPresenter($value) !== null;
    }

    /**
     * Present a reference to the value.
     *
     *
     * @param mixed $value
     *
     * @throws \InvalidArgumentException If no Presenter is registered for $value
     * @return string
     */
    public function presentRef($value)
    {
        if ($presenter = $this->getPresenter($value)) {
            return $presenter->presentRef($value);
        }

        throw new \InvalidArgumentException(sprintf('Unable to present %s', $value));
    }

    /**
     * Present a full representation of the value.
     *
     *
     * @param mixed $value
     * @param int   $depth   (default: null)
     * @param int   $options One of Presenter constants
     *
     * @throws \InvalidArgumentException If no Presenter is registered for $value
     * @return string
     */
    public function present($value, $depth = null, $options = 0)
    {
        if ($presenter = $this->getPresenter($value)) {
            return $presenter->present($value, $depth, $options);
        }

        throw new \InvalidArgumentException(sprintf('Unable to present %s', $value));
    }

    /**
     * IteratorAggregate interface.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator(array_reverse($this->presenters));
    }

    /**
     * Find the highest precedence Presenter available for $value.
     *
     * Returns null if none is present.
     *
     * @param mixed $value
     *
     * @return null|Presenter
     */
    protected function getPresenter($value)
    {
        foreach ($this->presenters as $presenter) {
            if ($presenter->canPresent($value)) {
                return $presenter;
            }
        }
    }
}
