<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2019 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\ParserFactory;

class ParserFactoryTest extends \PhpUnit\Framework\TestCase
{
    public function testGetPossibleKinds()
    {
        $kinds = ParserFactory::getPossibleKinds();
        $this->assertContains(ParserFactory::PREFER_PHP7, $kinds);
        foreach ($kinds as $kind) {
            $this->assertTrue(defined("Psy\\ParserFactory::$kind"));
        }
    }

    public function testHasKindsSupport()
    {
        $factory = new ParserFactory();
        $this->assertEquals(class_exists('PhpParser\ParserFactory'), $factory->hasKindsSupport());
    }

    public function testGetDefaultKind()
    {
        $factory = new ParserFactory();

        if (!class_exists('PhpParser\ParserFactory')) {
            $this->assertNull($factory->getDefaultKind());

            return;
        }

        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $this->assertEquals(ParserFactory::ONLY_PHP7, $factory->getDefaultKind());
        } else {
            $this->assertEquals(ParserFactory::ONLY_PHP5, $factory->getDefaultKind());
        }
    }

    public function testCreateParser()
    {
        $factory = new ParserFactory();

        $parser = $factory->createParser();
        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $this->assertInstanceOf('PhpParser\Parser\Php7', $parser);
        } else {
            $this->assertInstanceOf('PhpParser\Parser\Php5', $parser);
        }
    }
}
