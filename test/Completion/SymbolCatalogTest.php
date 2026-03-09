<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion;

use Psy\Completion\SymbolCatalog;
use Psy\Test\TestCase;

class SymbolCatalogTest extends TestCase
{
    public function testCatalogRefreshesAfterNewClassIsDeclared(): void
    {
        $catalog = new SymbolCatalog();
        $beforeCount = \count($catalog->getClasses());

        $className = 'CompletionCatalogRefreshTest'.\uniqid();
        eval('class '.$className.' {}');

        $classes = $catalog->getClasses();

        $this->assertContains($className, $classes);
        $this->assertGreaterThanOrEqual($beforeCount + 1, \count($classes));
    }
}
