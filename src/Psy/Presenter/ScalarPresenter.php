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
 * A scalar (and null) Presenter.
 */
class ScalarPresenter extends AbstractPresenter
{
    /**
     * Scalar presenter can present scalars and null.
     *
     * Technically this would make it a ScalarOrNullPresenter, but that's a much
     * lamer name :)
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return is_scalar($value) || is_null($value);
    }

    /**
     * Present the scalar value.
     *
     * @param mixed $value
     * @param int   $depth (default: null)
     *
     * @return string
     */
    public function present($value, $depth = null)
    {
        return json_encode($value);
    }
}
