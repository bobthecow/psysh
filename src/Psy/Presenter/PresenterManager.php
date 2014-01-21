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
        $this->addPresenter(new ObjectPresenter); // lowest precedence
        $this->addPresenter(new ArrayPresenter);
        $this->addPresenter(new ClosurePresenter);
        $this->addPresenter(new ExceptionPresenter);
        $this->addPresenter(new ResourcePresenter);
        $this->addPresenter(new ScalarPresenter);
    }

    /**
     * Register a Presenter.
     *
     * If multiple Presenters are able to present a value, the most recently
     * registered Presenter takes precedence.
     *
     * If $presenter is already registered, it is be re-registered as the
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

        return false;
    }

    /**
     * Present a reference to the value.
     *
     * @throws InvalidArugmentException If no Presenter is registered for $value
     *
     * @param mixed $value
     *
     * @return string
     */
    public function presentRef($value, $color = false)
    {
        if ($presenter = $this->getPresenter($value)) {
            return $presenter->presentRef($value, $color);
        }

        throw new \InvalidArgumentException(sprintf('Unable to present %s', $value));
    }

    /**
     * Present a full representation of the value.
     *
     * @throws InvalidArgumentException If no Presenter is registered for $value
     *
     * @param mixed $value
     * @param int   $depth (default: null)
     *
     * @return string
     */
    public function present($value, $depth = null)
    {
        if ($presenter = $this->getPresenter($value)) {
            return $presenter->present($value, $depth);
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
