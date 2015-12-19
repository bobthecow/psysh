<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\Command\WhereamiCommand;

class WhereamiCommandTest extends \PHPUnit_Framework_TestCase
{

    public function forceColorProvider()
    {
        return [
            'false' => [false, false],
            'true' => [true, true],
        ];
    }

    /**
     * @dataProvider forceColorProvider
     */
    public function testForceColor($expectation, $forceColor)
    {
        $command = new WhereamiCommand();
        $command->setForceColor($forceColor);

        $this->assertSame($expectation, $command->forceColor());
    }
}
