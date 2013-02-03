<?php

/*
 * This file is part of PsySH
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\ObjectFormatter;

class ObjectFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testFormat()
    {
        $empty = new \stdClass;

        $this->assertEquals(ObjectFormatter::formatRef($empty) . ' {}', ObjectFormatter::format($empty));

        $obj = new \stdClass;
        $obj->name = 'std';
        $obj->type = 'class';
        $obj->tags = array('stuff', 'junk');
        $obj->child = new \stdClass;
        $obj->child->name = 'std, jr';

        $formatted = ObjectFormatter::format($obj);

        $this->assertContains(ObjectFormatter::formatRef($obj), $formatted);
        $this->assertContains('name: "std"', $formatted);
        $this->assertContains('type: "class"', $formatted);
        $this->assertContains(ObjectFormatter::formatRef($obj->child), $formatted);
        $this->assertNotContains('std, jr', $formatted);
        $this->assertContains('Array(2)', $formatted);
        $this->assertNotContains('stuff', $formatted);
        $this->assertNotContains('junk', $formatted);
    }

    public function testFormatRef()
    {
        $obj = new \stdClass;

        $formatted = ObjectFormatter::formatRef($obj);

        $this->assertStringStartsWith('<stdClass #', $formatted);
        $this->assertStringEndsWith('>', $formatted);
        $this->assertContains(spl_object_hash($obj), $formatted);
    }
}
