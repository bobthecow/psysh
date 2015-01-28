<?php

namespace Psy\Test\TabCompletion;

use Psy\Command\ListCommand;
use Psy\Command\ShowCommand;
use Psy\Context;
use Psy\TabCompletion\AutoCompleter;

class AutoCompleterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider classesInput
     */
    public function testClassesCompletion($line, $expect)
    {
        $commands = array(
            new ShowCommand(),
            new ListCommand(),
        );

        $context = new Context();
        $context->setAll(array('foo' => 12, 'bar' => new \DOMDocument()));
        $tabCompletion = new AutoCompleter($context, $commands);

        $code = $tabCompletion->processCallback('', 0, array(
           'line_buffer' => $line,
           'point'       => 0,
           'end'         => strlen($line),
        ));

        $this->assertContains($expect, $code);
    }

    public function classesInput()
    {
        return array(
            array('T_OPE', 'T_OPEN_TAG'),
            array('stdC', 'stdClass'),
            array('stdC', 'stdClass'),
            array('stdC', 'stdClass'),
            array('s', 'stdClass'),
            array('s', 'stdClass'),
            array('array_', 'array_search'),
            array('$bar->', 'load'),
            array('$b', 'bar' ),
            array('6 + $b', 'bar' ),
            array('$f', 'foo' ),
            array('l', 'ls'),
            array('sho', 'show'),
            array('12 + clone ', 'foo' ),
            array(
                'Psy\Test\TabCompletion\StaticSample::CO',
                'Psy\Test\TabCompletion\StaticSample::CONSTANT_VALUE',
            ),
            array(
                'Psy\Test\TabCompletion\StaticSample::',
                'Psy\Test\TabCompletion\StaticSample::$staticVariable',
            ),
            array(
                'Psy\Test\TabCompletion\StaticSample::',
                'Psy\Test\TabCompletion\StaticSample::staticFunction',
            ),
        );
    }
}
