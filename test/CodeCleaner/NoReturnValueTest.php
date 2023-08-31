<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PhpParser\Node\Stmt\Expression;
use Psy\CodeCleaner\NoReturnValue;
use Psy\Test\ParserTestCase;

/**
 * @group isolation-fail
 */
class NoReturnValueTest extends ParserTestCase
{
    public function testCreate()
    {
        $this->assertSame(
            $this->prettyPrint($this->parse('new \\Psy\CodeCleaner\\NoReturnValue()')),
            $this->prettyPrint([new Expression(NoReturnValue::create())])
        );
    }
}
