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

use Psy\Presenter\AbstractPresenter;

/**
 * A resource Presenter.
 */
class ResourcePresenter extends AbstractPresenter
{
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
     * Present the resource.
     *
     * @param resource $value
     * @param int      $depth (default: null)
     *
     * @return string
     */
    public function present($value, $depth = null)
    {
        $type = get_resource_type($value);
        if ($type === 'stream') {
            $meta = stream_get_meta_data($value);
            $type = sprintf('%s stream', $meta['stream_type']);
        }

        $id = str_replace('Resource id #', '', (string) $value);

        return sprintf('<%s resource #%s>', $type, $id);
    }
}
