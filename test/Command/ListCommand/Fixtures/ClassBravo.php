<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand\Fixtures;

class ClassBravo implements InterfaceDelta
{
    const B = 'bee';

    public $foo;
    protected $bar;
    private $baz;

    public function foo()
    {
    }

    protected function bar()
    {
    }

    private function baz()
    {
    }
}
