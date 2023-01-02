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

use Psy\Reflection\ReflectionLanguageConstruct;
use Psy\Reflection\ReflectionLanguageConstructParameter;

class ReflectionLanguageConstructParameterTest extends \Psy\Test\TestCase
{
    public function testOptions()
    {
        $keyword = new ReflectionLanguageConstruct('die');

        $refl = new ReflectionLanguageConstructParameter($keyword, 'one', [
            'isArray'             => false,
            'defaultValue'        => null,
            'isOptional'          => false,
            'isPassedByReference' => false,
        ]);

        $this->assertNull($refl->getClass());
        $this->assertSame('one', $refl->getName());
        $this->assertFalse($refl->isArray());
        $this->assertTrue($refl->isDefaultValueAvailable());
        $this->assertNull($refl->getDefaultValue());
        $this->assertFalse($refl->isOptional());
        $this->assertFalse($refl->isPassedByReference());

        $reflTwo = new ReflectionLanguageConstructParameter($keyword, 'two', [
            'isArray'             => true,
            'isOptional'          => true,
            'isPassedByReference' => true,
        ]);

        $this->assertNull($refl->getClass());
        $this->assertSame('two', $reflTwo->getName());
        $this->assertTrue($reflTwo->isArray());
        $this->assertFalse($reflTwo->isDefaultValueAvailable());
        $this->assertNull($reflTwo->getDefaultValue());
        $this->assertTrue($reflTwo->isOptional());
        $this->assertTrue($reflTwo->isPassedByReference());

        $refl = new ReflectionLanguageConstructParameter($keyword, 'three', [
            'defaultValue' => 3,
        ]);

        $this->assertNull($refl->getClass());
        $this->assertSame('three', $refl->getName());
        $this->assertFalse($refl->isArray());
        $this->assertTrue($refl->isDefaultValueAvailable());
        $this->assertSame(3, $refl->getDefaultValue());
        $this->assertFalse($refl->isOptional());
        $this->assertFalse($refl->isPassedByReference());
    }
}
