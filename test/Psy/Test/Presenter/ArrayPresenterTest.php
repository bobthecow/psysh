<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Presenter;

use Psy\Presenter\ArrayPresenter;
use Psy\Presenter\ObjectPresenter;
use Psy\Presenter\PresenterManager;
use Psy\Presenter\ScalarPresenter;

class ArrayPresenterTest extends \PHPUnit_Framework_TestCase
{
    private $presenter;
    private $manager;

    public function setUp()
    {
        $this->presenter = new ArrayPresenter();

        $this->manager   = new PresenterManager();
        $this->manager->addPresenter(new ScalarPresenter());
        $this->manager->addPresenter(new ObjectPresenter());
        $this->manager->addPresenter($this->presenter);
    }

    /**
     * @dataProvider presentData
     */
    public function testPresent($array, $expect)
    {
        $this->assertEquals($expect, self::strip($this->presenter->present($array)));
    }

    public function presentData()
    {
        return array(
            array(array(),                   '[]'),
            array(array(1),                  '[<number>1</number>]'),
            array(array(2, "string"),        '[<number>2</number>,<string>"string"</string>]'),
            array(array('a' => 1, 'b' => 2), '[<string>"a"</string>=><number>1</number>,<string>"b"</string>=><number>2</number>]'),
        );
    }

    /**
     * @dataProvider presentRefData
     */
    public function testPresentRef($array, $expect)
    {
        $this->assertEquals($expect, $this->presenter->presentRef($array));
    }

    public function presentRefData()
    {
        return array(
            array(array(),        '[]'),
            array(array(1),       'Array(<number>1</number>)'),
            array(array(1, 2),    'Array(<number>2</number>)'),
            array(array(1, 2, 3), 'Array(<number>3</number>)'),
        );
    }

    /**
     * @dataProvider presentArrayObjectsData
     */
    public function testPresentArrayObjects($arrayObj, $expect, $expectRef)
    {
        $this->assertEquals($expect,    $this->presenter->present($arrayObj));
        $this->assertEquals($expectRef, $this->presenter->presentRef($arrayObj));
    }

    public function presentArrayObjectsData()
    {
        $obj1    = new \ArrayObject(array(1, "string"));
        $hash1   = spl_object_hash($obj1);
        $ref1    = '<object>\\<<class>ArrayObject</class> <strong>#' . $hash1 . '</strong>></object>';
        $expect1 = <<<EOS
$ref1 [
    <number>1</number>,
    <string>"string"</string>
]
EOS;

        $obj2    = new FakeArrayObject(array('a' => 'AAA', 'b' => 'BBB'));
        $hash2   = spl_object_hash($obj2);
        $ref2    = '<object>\\<<class>Psy\\Test\\Presenter\\FakeArrayObject</class> <strong>#' . $hash2 . '</strong>></object>';
        $expect2 = <<<EOS
$ref2 [
    <string>"a"</string> => <string>"AAA"</string>,
    <string>"b"</string> => <string>"BBB"</string>
]
EOS;

        return array(
            array($obj1, $expect1, $ref1),
            array($obj2, $expect2, $ref2),
        );
    }

    public function testPresentsRecursively()
    {
        $obj      = new \StdClass();
        $array    = array(1, $obj, "a");
        $hash     = spl_object_hash($obj);
        $expected = <<<EOS
[
    <number>1</number>,
    <object>\<<class>stdClass</class> <strong>#$hash</strong>></object> {},
    <string>"a"</string>
]
EOS;

        $this->assertEquals($expected, $this->presenter->present($array));
    }

    private static function strip($text)
    {
        return preg_replace('/\\s/', '', $text);
    }
}
