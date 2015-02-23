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

/**
 * A Mongo Cursor Presenter.
 */
class MongoCursorPresenter extends ObjectPresenter
{
    private static $boringFields = array('limit', 'batchSize', 'skip', 'flags');
    private static $ignoreFields = array('server', 'host', 'port', 'connection_type_desc');

    /**
     * MongoCursorPresenter can present Mongo Cursors.
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
     * @param int              $propertyFilter One of \ReflectionProperty constants
     *
     * @return array
     */
    protected function getProperties($value, \ReflectionClass $class, $propertyFilter)
    {
        $info = $value->info();

        $this->normalizeQueryArray($info);
        $this->normalizeFieldsArray($info);
        $this->unsetBoringFields($info);
        $this->unsetIgnoredFields($info);

        if ($value->dead()) {
            $info['dead'] = true;
        }

        return array_merge(
            $info,
            parent::getProperties($value, $class, $propertyFilter)
        );
    }

    /**
     * Normalize (empty) cursor query to always be an actual array.
     *
     * @param array $info Cursor info
     */
    private function normalizeQueryArray(array &$info)
    {
        if (isset($info['query'])) {
            if ($info['query'] === new \StdClass()) {
                $info['query'] = array();
            } elseif (is_array($info['query']) && isset($info['query']['$query'])) {
                if ($info['query']['$query'] === new \StdClass()) {
                    $info['query']['$query'] = array();
                }
            }
        }
    }

    /**
     * Normalize (empty) cursor fields to always be an actual array.
     *
     * @param array $info Cursor info
     */
    private function normalizeFieldsArray(array &$info)
    {
        if (isset($info['fields']) && $info['fields'] === new \StdClass()) {
            $info['fields'] = array();
        }
    }

    /**
     * Unset boring fields from the Cursor info array.
     *
     * @param array $info Cursor info
     */
    private function unsetBoringFields(array &$info)
    {
        foreach (self::$boringFields as $boring) {
            if ($info[$boring] === 0) {
                unset($info[$boring]);
            }
        }
    }

    /**
     * Unset ignored fields from the Cursor info array.
     *
     * @param array $info Cursor info
     */
    private function unsetIgnoredFields(array &$info)
    {
        foreach (self::$ignoreFields as $ignore) {
            if (isset($info[$ignore])) {
                unset($info[$ignore]);
            }
        }
    }
}
