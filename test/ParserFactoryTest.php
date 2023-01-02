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

use PhpParser\ParserFactory as OriginalParserFactory;
use Psy\ParserFactory;

class ParserFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testGetPossibleKinds()
    {
        $kinds = ParserFactory::getPossibleKinds();
        $this->assertContains(ParserFactory::PREFER_PHP7, $kinds);
        foreach ($kinds as $kind) {
            $this->assertTrue(\defined("Psy\\ParserFactory::$kind"));
        }
    }

    public function testGetDefaultKind()
    {
        $factory = new ParserFactory();

        if (!\class_exists(OriginalParserFactory::class)) {
            $this->assertNull($factory->getDefaultKind());

            return;
        }

        $this->assertSame(ParserFactory::ONLY_PHP7, $factory->getDefaultKind());
    }

    public function testCreateParser()
    {
        $factory = new ParserFactory();

        $parser = $factory->createParser();
        $this->assertInstanceOf(\PhpParser\Parser\Php7::class, $parser);
    }
}
