<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\TabCompletion;

use Psy\Command\ListCommand;
use Psy\Command\ShowCommand;
use Psy\Configuration;
use Psy\Context;
use Psy\ContextAware;
use Psy\TabCompletion\Matcher;

class AutoCompleterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param $line
     * @param $mustContain
     * @param $mustNotContain
     * @dataProvider classesInput
     */
    public function testClassesCompletion($line, $mustContain, $mustNotContain)
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

        foreach ($mustContain as $mc) {
            $this->assertContains($mc, $code);
        }

        foreach ($mustNotContain as $mnc) {
            $this->assertNotContains($mnc, $code);
        }
    }

    /**
     * TODO
     * ====
     * draft, open to modifications
     * - [ ] if the variable is an array, return the square bracket for completion
     * - [ ] if the variable is a constructor or method, reflect to complete as a function call
     * - [ ] if the preceding token is a variable, call operators or keywords compatible for completion
     * - [X] a command always should be the second token after php_open_tag
     * - [X] keywords are never consecutive
     * - [X] namespacing completion should work just fine
     * - [X] after a new keyword, should always be a class constructor, never a function call or keyword, constant,
     *       or variable that does not contain a existing class name.
     * - [X] on a namespaced constructor the completion must show the classes related, not constants.
     *
     * @return array
     */
    public function classesInput()
    {
        return array(
            // input, must had, must not had
            array('T_OPE', array('T_OPEN_TAG'), array()),
            array('st', array('stdClass'), array()),
            array('stdCla', array('stdClass'), array()),
            array('new s', array('stdClass'), array()),
            array(
                'new ',
                array('stdClass', 'Psy\\Context', 'Psy\\Configuration'),
                array('require', 'array_search', 'T_OPEN_TAG', '$foo'),
            ),
            array('new Psy\\C', array('Context'), array('CASE_LOWER')),
            array('\s', array('stdClass'), array()),
            array('array_', array('array_search', 'array_map', 'array_merge'), array()),
            array('$bar->', array('load'), array()),
            array('$b', array('bar'), array()),
            array('6 + $b', array('bar'), array()),
            array('$f', array('foo'), array()),
            array('l', array('ls'), array()),
            array('ls ', array(), array('ls')),
            array('sho', array('show'), array()),
            array('12 + clone $', array('foo'), array()),
            // array(
            //   '$foo ',
            //   array('+', 'clone'),
            //   array('$foo', 'DOMDocument', 'array_map')
            // ), requires a operator matcher?
            array('$', array('foo', 'bar'), array('require', 'array_search', 'T_OPEN_TAG', 'Psy')),
            array(
                'Psy\\',
                array('Context', 'TabCompletion\\Matcher\\AbstractMatcher'),
                array('require', 'array_search'),
            ),
            array(
                'Psy\Test\TabCompletion\StaticSample::CO',
                array('Psy\Test\TabCompletion\StaticSample::CONSTANT_VALUE'),
                array(),
            ),
            array(
                'Psy\Test\TabCompletion\StaticSample::',
                array('Psy\Test\TabCompletion\StaticSample::$staticVariable'),
                array(),
            ),
            array(
                'Psy\Test\TabCompletion\StaticSample::',
                array('Psy\Test\TabCompletion\StaticSample::staticFunction'),
                array(),
            ),
        );
    }
}
