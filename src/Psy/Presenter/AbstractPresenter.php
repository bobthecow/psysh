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
 * A simple abstract Presenter implementation which delegates self::presentRef()
 * to self::present().
 */
abstract class AbstractPresenter implements Presenter
{
    /**
     * Present a reference to the value.
     *
     * Delegates to self::present()
     *
     * @see Presenter::present()
     *
     * @param mixed $value
     *
     * @return string
     */
    public function presentRef($value)
    {
        return $this->present($value);
    }
}
