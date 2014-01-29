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

/**
 * An array Presenter.
 */
class ArrayPresenter extends RecursivePresenter
{
    /**
     * ArrayPresenter can present arrays.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return is_array($value) || $value instanceof \ArrayObject;
    }

    /**
     * Present a reference to the array.
     *
     * @param array $value
     *
     * @return string
     */
    public function presentRef($value, $color = false)
    {
        if (empty($value)) {
            return '[]';
        } elseif ($color) {
            return sprintf('Array(<number>%d</number>)', count($value));
        } else {
            return sprintf('Array(%d)', count($value));
        }
    }

    /**
     * Present the array.
     *
     * @param object $value
     * @param int    $depth (default: null)
     * @param bool   $color (default: false)
     *
     * @return string
     */
    protected function presentValue($value, $depth = null, $color = false)
    {
        if (empty($value) || $depth === 0) {
            return $this->presentRef($value, $color);
        }

        $formatted = array();
        foreach ($value as $key => $val) {
            $formatted[$key] = $this->presentSubValue($val, $color);
        }

        if ($this->shouldShowKeys($value)) {
            $pad = max(array_map('strlen', array_map(array('Psy\Util\Json', 'encode'), array_keys($value))));
            foreach ($formatted as $key => $val) {
                $formatted[$key] = $this->formatKeyAndValue($key, $val, $pad, $color);
            }
        } else {
            $formatted = array_map(array($this, 'indentValue'), $formatted);
        }

        $template = sprintf('[%s%s%%s%s]', PHP_EOL, self::INDENT, PHP_EOL);
        $glue     = sprintf(',%s%s', PHP_EOL, self::INDENT);

        return sprintf($template, implode($glue, $formatted));
    }

    /**
     * Helper method for determining whether to render array keys.
     *
     * Keys are only rendered for associative arrays or non-consecutive integer-
     * based arrays.
     *
     * @param array $array
     *
     * @return boolean
     */
    protected function shouldShowKeys(array $array)
    {
        $i = 0;
        foreach (array_keys($array) as $k) {
            if ($k !== $i++) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a key => value pair.
     *
     * @param mixed   $key
     * @param string  $value
     * @param integer $pad   Maximum key width, to align the hashrockets.
     * @param bool    $color (default: false)
     *
     * @return string
     */
    protected function formatKeyAndValue($key, $value, $pad = 0, $color = false)
    {
        if ($color) {
            $type = is_string($value) ? 'string' : 'number';
            $tpl  = "<$type>%-${pad}s</$type> => %s";
        } else {
            $tpl = "%-${pad}s => %s";
        }

        return sprintf(
            $tpl,
            Json::encode($key),
            $this->indentValue($value)
        );
    }
}
