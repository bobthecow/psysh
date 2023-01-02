<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Input;

use Psy\Input\CodeArgument;
use Symfony\Component\Console\Input\InputArgument;

class CodeArgumentTest extends \Psy\Test\TestCase
{
    /**
     * @dataProvider getInvalidModes
     */
    public function testInvalidModes($mode)
    {
        $this->expectException(\InvalidArgumentException::class);
        new CodeArgument('wat', $mode);

        $this->fail();
    }

    public function getInvalidModes()
    {
        return [
            [InputArgument::IS_ARRAY],
            [InputArgument::IS_ARRAY | InputArgument::REQUIRED],
            [InputArgument::IS_ARRAY | InputArgument::OPTIONAL],
        ];
    }

    /**
     * @dataProvider getValidModes
     */
    public function testValidModes($mode)
    {
        $this->assertInstanceOf(CodeArgument::class, new CodeArgument('yeah', $mode));
    }

    public function getValidModes()
    {
        return [
            [InputArgument::REQUIRED],
            [InputArgument::OPTIONAL],
        ];
    }
}
