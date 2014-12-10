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

/**
 * An array Presenter.
 */
class ArrayPresenter extends RecursivePresenter
{
    const ARRAY_OBJECT_FMT = '<object>\\<<class>%s</class> <strong>#%s</strong>></object>';

    /**
     * ArrayPresenter can present arrays.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return is_array($value) || $this->isArrayObject($value);
    }

    /**
     * Determine whether something is an ArrayObject.
     *
     * This is a useful extension point for Presenter subclasses for Array-like
     * objects which aren't necessarily subclasses of ArrayObject.
     *
     * @return boolean
     */
    protected function isArrayObject($value)
    {
        return $value instanceof \ArrayObject;
    }

    /**
     * Present a reference to the array.
     *
     * @param array $value
     *
     * @return string
     */
    public function presentRef($value)
    {
        if ($this->isArrayObject($value)) {
            return $this->presentArrayObjectRef($value);
        } elseif (empty($value)) {
            return '[]';
        } else {
            return sprintf('Array(<number>%d</number>)', count($value));
        }
    }

    /**
     * Present a reference to an ArrayObject
     *
     * @param ArrayObject $value
     *
     * @return string
     */
    protected function presentArrayObjectRef($value)
    {
        return sprintf(self::ARRAY_OBJECT_FMT, get_class($value), spl_object_hash($value));
    }

    /**
     * Get an array of values from an ArrayObject.
     *
     * This is a useful extension point for Presenter subclasses for Array-like
     * objects which aren't necessarily subclasses of ArrayObject.
     *
     * @return array
     */
    protected function getArrayObjectValue($value)
    {
        return iterator_to_array($value->getIterator());
    }

    /**
     * Present the array.
     *
     * @param object $value
     * @param int    $depth   (default: null)
     * @param int    $options One of Presenter constants
     *
     * @return string
     */
    protected function presentValue($value, $depth = null, $options = 0)
    {
        $prefix = '';
        if ($this->isArrayObject($value)) {
            $prefix = $this->presentArrayObjectRef($value) . ' ';
            $value  = $this->getArrayObjectValue($value);
        }

        if (empty($value) || $depth === 0) {
            return $prefix . $this->presentRef($value);
        }

        $formatted = array();
        foreach ($value as $key => $val) {
            $formatted[$key] = $this->presentSubValue($val);
        }

        if ($this->shouldShowKeys($value)) {
            $pad = max(array_map('strlen', array_map(array('Psy\Util\Json', 'encode'), array_keys($value))));
            foreach ($formatted as $key => $val) {
                $formatted[$key] = $this->formatKeyAndValue($key, $val, $pad);
            }
        } else {
            $formatted = array_map(array($this, 'indentValue'), $formatted);
        }

        $template = sprintf('%s[%s%s%%s%s]', $prefix, PHP_EOL, self::INDENT, PHP_EOL);
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
     *
     * @return string
     */
    protected function formatKeyAndValue($key, $value, $pad = 0)
    {
        $type = is_string($value) ? 'string' : 'number';
        $tpl  = "<$type>%-${pad}s</$type> => %s";

        return sprintf(
            $tpl,
            Json::encode($key),
            $this->indentValue($value)
        );
    }
}
