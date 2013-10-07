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

use Psy\Presenter\Presenter;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * A resource Presenter.
 */
class ResourcePresenter implements Presenter
{
    const FMT       = '%s%s resource #%s>';
    const COLOR_FMT = '<resource>%s%s <strong>resource #%s</strong>></resource>';

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

        return sprintf($format, OutputFormatter::escape('<'), $type, $id);
    }

    /**
     * Present the resource.
     *
     * @param resource $value
     * @param int      $depth (default: null)
     *
     * @return string
     */
    public function present($value, $depth = null)
    {
        return $this->presentRef($value, false);
    }
}
