<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Presenter;

/**
 * A Mongo Cursor Presenter.
 */
class MongoCursorPresenter extends ObjectPresenter
{
    private static $boringFields = array('limit', 'batchSize', 'skip', 'flags');
    private static $ignoreFields = array('server', 'host', 'port', 'connection_type_desc');

    /**
     * ObjectPresenter can present Mongo Cursors.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return $value instanceof \MongoCursor;
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
        $empty = new \StdClass;
        $props = array();

        $info  = $value->info();

        if ($info['query'] == new \StdClass) {
            $info['query'] = array();
        }

        if (isset($info['query'])) {
            if (isset($info['query']['$query'])) {
                if ($info['query']['$query'] == $empty) {
                    $info['query']['$query'] = array();
                }
            } elseif ($info['query'] == $empty) {
                $info['query'] = array();
            }
        }

        if (isset($info['fields']) && $info['fields'] == $empty) {
            $info['fields'] = array();
        }


        foreach (self::$boringFields as $boring) {
            if ($info[$boring] === 0) {
                unset($info[$boring]);
            }
        }

        foreach (self::$ignoreFields as $ignore) {
            if (isset($info[$ignore])) {
                unset($info[$ignore]);
            }
        }

        if ($value->dead()) {
            $info['dead'] = true;
        }

        return array_merge(
            $info,
            parent::getProperties($value, $class)
        );
    }
}
