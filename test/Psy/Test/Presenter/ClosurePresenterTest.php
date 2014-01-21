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

use Psy\Presenter\ClosurePresenter;
use Psy\Presenter\ObjectPresenter;
use Psy\Presenter\PresenterManager;
use Psy\Presenter\ScalarPresenter;

class ClosurePresenterTest extends \PHPUnit_Framework_TestCase
{
    private $presenter;
    private $manager;

    public function setUp()
    {
        $this->presenter = new ClosurePresenter;

        $this->manager   = new PresenterManager;
        $this->manager->addPresenter(new ScalarPresenter);
        $this->manager->addPresenter(new ObjectPresenter);
        $this->manager->addPresenter($this->presenter);
    }

    /**
     * @dataProvider presentData
     */
    public function testPresent($closure, $expect)
    {
        $this->assertEquals($expect, $this->presenter->present($closure));
    }

    /**
     * @dataProvider presentData
     */
    public function testPresentRef($closure, $expect)
    {
        $this->assertEquals($expect, $this->presenter->presentRef($closure));
    }

    public function presentData()
    {
        $eol = version_compare(PHP_VERSION, '5.4.3', '>=') ? 'PHP_EOL' : '"\n"';

        return array(
            array(function () {},                  'function () { ... }'                   ),
            array(function ($foo) {},              'function ($foo) { ... }'               ),
            array(function ($foo, $bar = null) {}, 'function ($foo, $bar = null) { ... }'  ),
            array(function ($foo = "bar") {},      'function ($foo = "bar") { ... }'       ),
            array(function ($foo = \PHP_EOL) {},   'function ($foo = ' . $eol . ') { ... }'),
        );
    }
}
