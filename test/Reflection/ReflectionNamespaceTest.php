<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Reflection;

use Psy\Reflection\ReflectionNamespace;

class ReflectionNamespaceTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruction()
    {
        $refl = new ReflectionNamespace('Psy\\Test\\Reflection');

        $this->assertEquals('Psy\\Test\\Reflection', $refl->getName());
        $this->assertEquals('Psy\\Test\\Reflection', (string) $refl);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNotYetImplemented()
    {
        ReflectionNamespace::export('Psy\\Test\\Reflection');
    }
}
