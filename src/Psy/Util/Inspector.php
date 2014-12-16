<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Util;

/**
 * A utility class for inspecting objects.
 *
 * This has basically been deprecated by presenters, because they're a lot awesomer.
 */
class Inspector
{
    /**
     * Export all public, private and protected properties of $var.
     *
     * @param mixed $var   Variable to export.
     * @param int   $depth Maximum depth to export, or -1 for infinite. **danger, will robison** (default: -1)
     *
     * @return mixed
     */
    public static function export($var, $depth = 5)
    {
        if ($depth === 0) {
            if (is_object($var)) {
                return sprintf('<%s #%s>', get_class($var), spl_object_hash($var));
            } elseif (is_array($var)) {
                return sprintf('Array(%d)', count($var));
            } else {
                return $var;
            }
        } elseif (is_array($var)) {
            $return = array();
            foreach ($var as $k => $v) {
                $return[$k] = self::export($v, $depth - 1);
            }

            return $return;
        } elseif (is_object($var)) {
            $return = new \StdClass();
            $class  = new \ReflectionObject($var);
            $return->__CLASS__ = get_class($var);

            foreach ($class->getProperties() as $prop) {
                $name = $prop->getName();
                $prop->setAccessible(true);
                $return->$name = self::export($prop->getValue($var), $depth - 1);
            }

            return $return;
        } else {
            return $var;
        }
    }
}
