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
    const FMT       = '\\<%s resource #%s>';
    const COLOR_FMT = '<resource>\\<%s <strong>resource #%s</strong>></resource>';

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
    public function presentRef($value, $color = false)
    {
        $type = get_resource_type($value);
        if ($type === 'stream') {
            $meta = stream_get_meta_data($value);
            $type = sprintf('%s stream', $meta['stream_type']);
        }

        $id     = str_replace('Resource id #', '', (string) $value);
        $format = $color ? self::COLOR_FMT : self::FMT;

        return sprintf($format, $type, $id);
    }

    /**
     * Present the resource.
     *
     * @param resource $value
     * @param int      $depth (default: null)
     * @param bool     $color (default: false)
     *
     * @return string
     */
    public function present($value, $depth = null, $color = false)
    {
        return $this->presentRef($value, $color);
    }
}
