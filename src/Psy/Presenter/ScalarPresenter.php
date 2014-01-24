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

use Psy\Util\Json;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * A scalar (and null) Presenter.
 */
class ScalarPresenter implements Presenter
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
     * Present a reference to the value.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function presentRef($value, $color = false)
    {
        return $this->present($value, null, $color);
    }

    /**
     * Present the scalar value.
     *
     * @param mixed $value
     * @param int   $depth (default: null)
     * @param bool  $color (default: false)
     *
     * @return string
     */
    public function present($value, $depth = null, $color = false)
    {
        $formatted = OutputFormatter::escape(Json::encode($value));

        if ($color && $typeStyle = $this->getTypeStyle($value)) {
            return sprintf('<%s>%s</%s>', $typeStyle, $formatted, $typeStyle);
        } else {
            return $formatted;
        }
    }

    /**
     * Get the output style for a value of a given type.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function getTypeStyle($value)
    {
        if (is_int($value) || is_float($value)) {
            return 'number';
        } elseif (is_string($value)) {
            return 'string';
        } elseif (is_bool($value) || is_null($value)) {
            return 'bool';
        }
    }
}
