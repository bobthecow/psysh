<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\CodeCleaner;

/**
 * @group isolation-fail
 */
class CodeCleanerResolveClassNameTest extends TestCase
{
    private $cleaner;

    public function setUp(): void
    {
        $this->cleaner = new CodeCleaner();
    }

    public function testResolveFullyQualifiedName()
    {
        $result = $this->cleaner->resolveClassName('\\Psy\\Shell');
        $this->assertSame('\\Psy\\Shell', $result);
    }

    public function testResolveWithExplicitUse()
    {
        $this->cleaner->clean(['use Psy\\Shell;']);
        $result = $this->cleaner->resolveClassName('Shell');
        $this->assertSame('\\Psy\\Shell', $result);
    }

    public function testResolveWithImplicitUse()
    {
        // ImplicitUsePass requires classes to be loaded via get_declared_classes()
        // Force the class to load so the test is deterministic
        \class_exists('Psy\\Shell', true);

        // Create cleaner with implicit use enabled
        $cleaner = new CodeCleaner(null, null, null, false, false, [
            'includeNamespaces' => ['Psy\\'],
        ]);

        $cleaner->clean(['$c = new Shell;']);
        $result = $cleaner->resolveClassName('Shell');
        $this->assertSame('\\Psy\\Shell', $result);
    }

    public function testResolveUnknownName()
    {
        // Unknown names should return unchanged
        $result = $this->cleaner->resolveClassName('UnknownClass');
        $this->assertSame('UnknownClass', $result);
    }

    public function testResolveInvalidName()
    {
        // Invalid class names should return unchanged (not attempted to parse)
        $result = $this->cleaner->resolveClassName('123Invalid');
        $this->assertSame('123Invalid', $result);

        $result = $this->cleaner->resolveClassName('has-dash');
        $this->assertSame('has-dash', $result);

        $result = $this->cleaner->resolveClassName('has space');
        $this->assertSame('has space', $result);

        $result = $this->cleaner->resolveClassName('has$dollar');
        $this->assertSame('has$dollar', $result);
    }

    public function testResolveQualifiedName()
    {
        $this->cleaner->clean(['use Psy\\Command;']);
        $result = $this->cleaner->resolveClassName('Command\\ShowCommand');
        $this->assertSame('\\Psy\\Command\\ShowCommand', $result);
    }
}
