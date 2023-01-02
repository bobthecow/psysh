<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Reflection;

use Psy\Reflection\ReflectionNamespace;

class ReflectionNamespaceTest extends \Psy\Test\TestCase
{
    public function testConstruction()
    {
        $refl = new ReflectionNamespace('Psy\\Test\\Reflection');

        $this->assertSame('Psy\\Test\\Reflection', $refl->getName());
        $this->assertSame('Psy\\Test\\Reflection', (string) $refl);
    }

    public function testNotYetImplemented()
    {
        $this->expectException(\RuntimeException::class);
        ReflectionNamespace::export('Psy\\Test\\Reflection');

        $this->fail();
    }
}
