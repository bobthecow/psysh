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

use Psy\Presenter\ScalarPresenter;

class ScalarPresenterTest extends \PHPUnit_Framework_TestCase
{
    private $presenter;

    public function setUp()
    {
        $this->presenter = new ScalarPresenter();
    }

    /**
     * @dataProvider scalarData
     */
    public function testPresent($value, $expect)
    {
        $this->assertEquals($expect, $this->presenter->present($value));
    }

    public function scalarData()
    {
        return array(
            array(1,       '<number>1</number>'),
            array(1.0,     '<number>1.0</number>'),
            array(1.5,     '<number>1.5</number>'),
            array('2',     '<string>"2"</string>'),
            array('2.5',   '<string>"2.5"</string>'),
            array('alpha', '<string>"alpha"</string>'),
            array("a\nb",  '<string>"a\\nb"</string>'),
            array(true,    '<bool>true</bool>'),
            array(false,   '<bool>false</bool>'),
            array(null,    '<bool>null</bool>'),
            array(NAN,     '<number>NAN</number>'), // heh.
            array(acos(8), '<number>NAN</number>'),
            array(INF,     '<number>INF</number>'),
            array(-INF,    '<number>-INF</number>'),
            array(log(0),  '<number>-INF</number>'),
        );
    }
}
