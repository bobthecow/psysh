<?php

namespace Psy\Test\TabCompletion;

use Psy\Context;
use Psy\TabCompletion\AutoCompleter;

class AutoCompleterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider classesInput
     */
    public function testClassesCompletion($word, $index, $line, $point, $end, $expect)
    {
        $context = new Context();
        $tabCompletion = new AutoCompleter($context);

        $code = $tabCompletion->processCallback($word, $index, array(
           'line_buffer' => $line,
           'point'       => $point,
           'end'         => $end,
        ));

        $this->assertContains($expect, $code);
    }

    public function classesInput()
    {
        return array(
            array('stdC', 4, 'new stdC',  7, 7, 'stdClass()'),
            array('stdC', 5, 'new \stdC', 8, 8, 'stdClass()'),
            array('stdC', 5, '(new stdC', 8, 8, 'stdClass()'),
            array('s',    7, 'new \a\\s', 8, 8, 'stdClass()'),
        );
    }
}
