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
 * A resource Presenter.
 */
class ResourcePresenter implements Presenter
{
    const FMT = '<resource>\\<%s <strong>resource #%s</strong>></resource>';

    /**
     * Resource presenter can present resources.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return is_resource($value);
    }

    /**
     * Present a reference to the value.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function presentRef($value)
    {
        $type = get_resource_type($value);
        if ($type === 'stream') {
            $meta = stream_get_meta_data($value);
            $type = sprintf('%s stream', $meta['stream_type']);
        }

        $id = str_replace('Resource id #', '', (string) $value);

        return sprintf(self::FMT, $type, $id);
    }

    /**
     * Present the resource.
     *
     * @param resource $value
     * @param int      $depth   (default: null)
     * @param int      $options One of Presenter constants
     *
     * @return string
     */
    public function present($value, $depth = null, $options = 0)
    {
        return $this->presentRef($value);
    }
}
