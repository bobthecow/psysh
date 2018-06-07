<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\NoReturnValue;
use Psy\Test\ParserTestCase;

class NoReturnValueTest extends ParserTestCase
{
    public function testCreate()
    {
        $code = [NoReturnValue::create()];
        $this->assertSame('new \\Psy\CodeCleaner\\NoReturnValue()', $this->prettyPrint($code));
    }
}
