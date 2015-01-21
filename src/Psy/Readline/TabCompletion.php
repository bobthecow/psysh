<?php

namespace Psy\Readline;

use Psy\Context;

/**
 * Class TabCompletion
 * @package Psy\Readline
 */
class TabCompletion
{
    /** @var Context */
    protected $context;

    public function __construct(Context $context = null)
    {
        $this->context = $context;
    }

    public function activate()
    {
        readline_completion_function(array(&$this, 'callback'));
    }

    /**
     * @param  $input
     * @param  $index
     * @param  array $info
     * @return array
     */
    public function processCallback($input, $index, $info = array())
    {
        // the char just before the cursor is a dollar? send a variable context
        $charAt = substr($info['line_buffer'], $index-1, 1);
        if ($charAt === '$') {
            return array_keys($this->context->getAll());
        }

        $parenthesize = function ($name) {
            return sprintf('%s()', $name);
        };

        if (strlen($info['line_buffer']) > 4) {
            // if the current position of the cursor has a precending new keyword send the classes names
            $newKeyword = substr($info['line_buffer'], $index - 4, 3);
            if ($newKeyword === 'new') {
                return array_map($parenthesize, array_filter(get_declared_classes(), function ($class) use ($input) {
                    return preg_match(sprintf('#^%s#', $input), $class);
                }));
            }
        }
        // for everything else send the functions names
        $functions = get_defined_functions();

        return array_map(
            $parenthesize,
            array_merge(
                $functions['internal'],
                $functions['user']
            )
        );
    }

    /**
     * @param $input
     * @param $index
     * @return array
     */
    public function callback($input, $index)
    {
        $this->processCallback($input, $index, readline_info());
    }
}
