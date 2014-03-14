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
        $this->presenter = new ArrayPresenter;

        $this->manager   = new PresenterManager;
        $this->manager->addPresenter(new ScalarPresenter);
        $this->manager->addPresenter(new ObjectPresenter);
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
            array(array(1),                  '[1]'),
            array(array(2, "string"),        '[2,"string"]'),
            array(array('a' => 1, 'b' => 2), '["a"=>1,"b"=>2]'),
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
            array(array(1),       'Array(1)'),
            array(array(1, 2),    'Array(2)'),
            array(array(1, 2, 3), 'Array(3)'),
        );
    }

    public function testPresentsRecursively()
    {
        $obj      = new \StdClass;
        $array    = array(1, $obj, "a");
        $hash     = spl_object_hash($obj);
        $expected = <<<EOS
[
    1,
    \<stdClass #$hash> {},
    "a"
]
EOS;

        $this->assertEquals($expected, $this->presenter->present($array));
    }

    private static function strip($text)
    {
        return preg_replace('/\\s/', '', $text);
    }
}
