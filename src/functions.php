<?php

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
function debugger(array $vars = array()) {
    echo "\n\n";

    $sh = new \Psy\Shell;
    $sh->setScopeVariables($vars);
    $sh->run();

    return $sh->getScopeVariables();
}
