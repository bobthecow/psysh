<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
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
        $this->presenter = new ScalarPresenter;
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
            array(1,       '1'),
            array(1.5,     '1.5'),
            array('2',     '"2"'),
            array('2.5',   '"2.5"'),
            array('alpha', '"alpha"'),
            array("a\nb",  '"a\\nb"'),
            array(true,    'true'),
            array(false,   'false'),
            array(null,    'null'),
            array(NAN,     'NAN'),
            array(acos(8), 'NAN'),
            array(INF,     'INF'),
            array(-INF,    '-INF'),
            array(log(0),  '-INF'),
        );
    }
}
