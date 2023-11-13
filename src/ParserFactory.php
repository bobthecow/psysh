<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use PhpParser\Parser;
use PhpParser\ParserFactory as OriginalParserFactory;

/**
 * Parser factory to abstract over PHP Parser library versions.
 */
class ParserFactory
{
    /**
     * New parser instance.
     */
    public function createParser(): Parser
    {
        $factory = new OriginalParserFactory();

        if (!\method_exists($factory, 'createForHostVersion')) {
            return $factory->create(OriginalParserFactory::PREFER_PHP7);
        }

        return $factory->createForHostVersion();
    }
}
