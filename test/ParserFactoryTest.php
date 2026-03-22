<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Psy\ParserFactory;

class ParserFactoryTest extends TestCase
{
    public function testCreateParser()
    {
        $factory = new ParserFactory();
        $parser = $factory->createParser();
        $this->assertInstanceOf(Parser::class, $parser);
    }
}
