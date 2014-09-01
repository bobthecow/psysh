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
     * @param bool $color
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
     * @param int $depth (default: null)
     * @param bool $color (default: false)
     * @param int $options One of Presenter constants
     *
     * @return string
     */
    public function present($value, $depth = null, $color = false, $options = 0)
    {
        $formatted = $this->format($value);

        if ($color && $typeStyle = $this->getTypeStyle($value)) {
            return sprintf('<%s>%s</%s>', $typeStyle, $formatted, $typeStyle);
        } else {
            return $formatted;
        }
    }

    private function format($value)
    {
        // Handle unencodable floats.
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NAN';
            } elseif (is_infinite($value)) {
                return $value === INF ? 'INF' : '-INF';
            }
        }

        return OutputFormatter::escape(Json::encode($value));
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
