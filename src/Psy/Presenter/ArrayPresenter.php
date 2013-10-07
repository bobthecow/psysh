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

use Psy\Presenter\RecursivePresenter;
use Psy\Util\Json;
use Symfony\Component\Console\Formatter\OutputFormatter;

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
     *
     * @return string
     */
    protected function presentValue($value, $depth = null)
    {
        if (empty($value) || $depth === 0) {
            return $this->presentRef($value);
        }

        $formatted = array_map(array($this, 'presentSubValue'), $value);

        if ($this->shouldShowKeys($value)) {
            $pad = max(array_map('strlen', array_map(array('Psy\Util\Json', 'encode'), array_keys($value))));
            array_walk($formatted, array($this, 'formatKeyAndValue'), $pad);
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
     * @param string  $value
     * @param mixed   $key
     * @param integer $pad   Maximum key width, to align the hashrockets.
     *
     * @return string
     */
    protected function formatKeyAndValue(&$value, $key, $pad = 0)
    {
        $value = sprintf(
            "%-${pad}s => %s",
            OutputFormatter::escape(Json::encode($key)),
            $this->indentValue($value)
        );
    }
}
