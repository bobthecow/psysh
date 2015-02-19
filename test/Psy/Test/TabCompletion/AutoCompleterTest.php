<?php

namespace Psy\Test\TabCompletion;

use Psy\Command\ListCommand;
use Psy\Command\ShowCommand;
use Psy\Configuration;
use Psy\Context;
use Psy\ContextAware;
use Psy\TabCompletion\Matcher;

class AutoCompleterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider classesInput
     */
    public function testClassesCompletion($line, $expect)
    {
        $context = new Context();

        $commands = array(
            new ShowCommand(),
            new ListCommand(),
        );

        $matchers = array(
            new Matcher\VariablesMatcher(),
            new Matcher\ClassNamesMatcher(),
            new Matcher\ConstantsMatcher(),
            new Matcher\FunctionsMatcher(),
            new Matcher\ObjectMethodsMatcher(),
            new Matcher\ObjectAttributesMatcher(),
            new Matcher\KeywordsMatcher(),
            new Matcher\ClassAttributesMatcher(),
            new Matcher\ClassMethodsMatcher(),
            new Matcher\CommandsMatcher($commands),
        );

        $config = new Configuration();
        $tabCompletion = $config->getAutoCompleter();
        foreach ($matchers as $matcher) {
            if ($matcher instanceof ContextAware) {
                $matcher->setContext($context);
            }
            $tabCompletion->addMatcher($matcher);
        }

        $context->setAll(array('foo' => 12, 'bar' => new \DOMDocument()));

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
            array('st', 'stdClass'),
            array('stdCla', 'stdClass'),
            array('new s', 'stdClass'),
            array('\s', 'stdClass'),
            array('array_', 'array_search'),
            array('$bar->', 'load'),
            array('$b', 'bar' ),
            array('6 + $b', 'bar' ),
            array('$f', 'foo' ),
            array('l', 'ls'),
            array('sho', 'show'),
            array('12 + clone $', 'foo' ),
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
