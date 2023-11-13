<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\ParserFactory;

/**
 * @group isolation-fail
 */
class ParserFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testCreateParser()
    {
        $factory = new ParserFactory();
        $parser = $factory->createParser();
        $this->assertInstanceOf(\PhpParser\Parser::class, $parser);
    }
}
