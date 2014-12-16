<?php

namespace Psy;

/**
 * Command to return the eval-able code to startup PsySH.
 *
 * ~~~php
 * eval(\Psy\sh());
 * ~~~
 *
 * @return string
 */
function sh()
{
    return "extract(\Psy\Shell::debug(get_defined_vars()));";
}
