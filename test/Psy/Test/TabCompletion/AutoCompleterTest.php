<?php

namespace Psy\Test\TabCompletion;

use Psy\Context;
use Psy\TabCompletion\AutoCompleter;

class StaticSample {
    const CONSTANT_VALUE = 12;

    public static $staticVariable;

    public static function staticFunction()
    {
        return self::CONSTANT_VALUE;
    }
}

class AutoCompleterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider classesInput
     */
    public function testClassesCompletion($word, $index, $line, $point, $end, $expect)
    {
        $context = new Context();
        $context->setAll(['foo' => 12, 'bar' => new \DOMDocument()]);
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
            array('stdC', 4, 'new stdC',  7, 7, 'stdClass'),
            array('stdC', 5, 'new \stdC', 8, 8, 'stdClass'),
            array('stdC', 5, '(new stdC', 8, 8, 'stdClass'),
            array('s',    7, 'new \a\\s', 8, 8, 'stdClass'),
            array('s',    1, '\s', 2, 2, 'stdClass'),
            array('array_', 6, 'array_', 6, 6, 'array_search'),
            array('', 6, '$bar->', 6, 6, 'load'),
            array('b', 2, '$b', 2, 2, 'bar' ),
            array('b', 6, '6 + $b', 6, 6, 'bar' ),
            array('f', 2, '$f', 2, 2, 'foo' ),
            array(
                'Psy\Test\TabCompletion\StaticSample::',
                37,
                'Psy\Test\TabCompletion\StaticSample::',
                37,
                37,
                'Psy\Test\TabCompletion\StaticSample::CONSTANT_VALUE'
            ),
            array(
                'Psy\Test\TabCompletion\StaticSample::',
                37,
                'Psy\Test\TabCompletion\StaticSample::',
                37,
                37,
                'Psy\Test\TabCompletion\StaticSample::$staticVariable'
            ),
        );
    }
}
