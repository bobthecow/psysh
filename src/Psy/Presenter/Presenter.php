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
 * Presenter classes are able to pretty-print values for display. Think
 * `var_dump`, but with sane and beautiful output.
 */
interface Presenter
{
    /**
     * Check whether this Presenter can present $value.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value);

    /**
     * Present a reference to the value.
     *
     * @param mixed   $value
     * @param Boolean $color
     *
     * @return string
     */
    public function presentRef($value, $color = false);

    /**
     * Present a full representation of the value.
     *
     * Optionally pass a $depth argument to limit the depth of recursive values.
     *
     * @param mixed $value
     * @param int   $depth (default: null)
     * @param bool  $color (default: false)
     *
     * @return string
     */
    public function present($value, $depth = null, $color = false);
}
