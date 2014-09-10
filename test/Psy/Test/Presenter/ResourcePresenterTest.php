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

use Psy\Presenter\ResourcePresenter;

class ResourcePresenterTest extends \PHPUnit_Framework_TestCase
{
    private $presenter;

    public function setUp()
    {
        $this->presenter = new ResourcePresenter();
    }

    public function testPresent()
    {
        $resource = fopen('php://stdin', 'r');
        $this->assertStringMatchesFormat('<resource>\<STDIO stream <strong>resource #%d</strong>></resource>', $this->presenter->present($resource));
        fclose($resource);
    }
}
