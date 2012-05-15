<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

require_once __DIR__.'/autoload.php';

/**
 * Invoke the PsySH debugger from the current context.
 *
 * For example:
 *
 *    foreach ($items as $item) {
 *        \Psy\debugger(get_defined_vars());
 *    }
 *
 * @param array $vars Scope variables from the calling context (default: array())
 *
 * @return array Scope variables from the debugger session.
 */
function debugger(array $vars = array())
{
    echo "\n\n";

    $sh = new \Psy\Shell;
    $sh->setScopeVariables($vars);
    $sh->run();

    return $sh->getScopeVariables();
}
