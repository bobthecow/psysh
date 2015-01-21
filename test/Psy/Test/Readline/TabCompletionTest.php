<?php

namespace Psy\Test\Readline;

use Psy\Readline\TabCompletion;
use Psy\Context;

/**
 * Class TabCompletionTest
 * @package Psy\Test\Readline
 */
class TabCompletionTest extends \PHPUnit_Framework_TestCase
{
    public function testClassesCompletion()
    {
        class_alias('\Psy\Test\Readline\TabCompletionTest', 'Foo');
        $context = new Context();
        $tabCompletion = new TabCompletion($context);
        $code = $tabCompletion->processCallback('stdC', 4, array(
           "line_buffer"               => "new stdC",
           "point"                     => 7,
           "end"                       => 7,
        ));
        $this->assertContains('stdClass()', $code);
    }
}
