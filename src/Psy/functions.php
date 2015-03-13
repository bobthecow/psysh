<?php

namespace Psy;

if (!function_exists('Psy\sh')) {
    /**
     * Command to return the eval-able code to startup PsySH.
     *
     *     eval(\Psy\sh());
     *
     * @return string
     */
    function sh()
    {
        return 'extract(\Psy\Shell::debug(get_defined_vars(), isset($this) ? $this : null));';
    }
}
