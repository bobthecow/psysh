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

use PhpParser\Node;

/**
 * A PhpParser Presenter.
 */
class PHPParserPresenter extends ObjectPresenter
{
    const FMT       = '\\<%s>';
    const COLOR_FMT = '<object>\\<<class>%s</class>></object>';

    /**
     * PHPParserPresenter can present parse trees.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return $value instanceof Node;
    }

    /**
     * Present a reference to the object.
     *
     * @param object $value
     *
     * @return string
     */
    public function presentRef($value, $color = false)
    {
        $format = $color ? self::COLOR_FMT : self::FMT;

        return sprintf($format, get_class($value));
    }

    /**
     * Get an array of object properties.
     *
     * @param object           $value
     * @param \ReflectionClass $class
     *
     * @return array
     */
    protected function getProperties($value, \ReflectionClass $class)
    {
        $props = array();

        $props['type']       = $value->getType();
        $props['attributes'] = $value->getAttributes();

        foreach ($value->getSubNodeNames() as $name) {
            $props[$name] = $value->$name;
        }

        return $props;
    }
}
