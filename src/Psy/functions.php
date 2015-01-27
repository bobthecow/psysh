<?php

namespace Psy;

use SuperClosure\Analyzer\TokenAnalyzer;

/**
 * Command to return the eval-able code to startup PsySH.
 *
 *     eval(\Psy\sh());
 *
 * @return string
 */
function sh()
{
    return 'extract(\Psy\Shell::debug(get_defined_vars(), $this ?: null));';
}

function dbg()
{
    list(,$caller) = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);

    $object = null;
    if (array_key_exists('object', $caller)) {
        $object = $caller['object'];
        $method = $caller['function'];
        $args = $caller['args'];
        $reflectionMethod = new \ReflectionMethod($object, $caller['function']);
        $closure = $reflectionMethod->getClosure($object);

        $analyzer = new TokenAnalyzer();
        $result = $analyzer->analyze($closure);
        $code = $result['code'];
        $code = str_replace('\Psy\brk();', '\Psy\Shell::debug(get_defined_vars(), $this?: null);', $code);
        eval($code);

        if ($result['hasThis']) {
            $reflectionMethod = new \ReflectionFunction($method);
            call_user_func_array(
                \Closure::bind(
                    $reflectionMethod->getClosure(),
                    $result['binding'],
                    get_class($result['binding'])
                ),
                $args
            );
        } else {
            call_user_func_array($method, $args);
        }
    }
}
