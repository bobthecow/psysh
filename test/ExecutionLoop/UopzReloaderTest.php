<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\ExecutionLoop;

use Psy\ExecutionLoop\UopzReloader;
use Psy\Shell;
use Psy\Test\TestCase;

class UopzReloaderTest extends TestCase
{
    private $testFile;
    private $tempFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        if (!UopzReloader::isSupported()) {
            $this->markTestSkipped('uopz extension required for UopzReloader tests');
        }

        $this->testFile = \tempnam(\sys_get_temp_dir(), 'psysh_reload_test_');
        $this->tempFiles[] = $this->testFile;
    }

    public function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->tempFiles as $file) {
            if (\file_exists($file)) {
                @\unlink($file);
            }
        }
    }

    public function testIsSupported()
    {
        $this->assertSame(\extension_loaded('uopz'), UopzReloader::isSupported());
    }

    public function testReloadMethod()
    {
        // Create initial file
        \file_put_contents($this->testFile, '<?php
class UopzTestClass {
    public function getValue() {
        return "original";
    }
}
');

        require $this->testFile;

        $obj = new \UopzTestClass();
        $this->assertEquals('original', $obj->getValue());

        // Create reloader and track initial state
        $reloader = new UopzReloader();
        $shell = $this->getShell();
        $reloader->onInput($shell, 'test');

        // Modify file with future timestamp to trigger reload
        $this->writeFileForReload($this->testFile, '<?php
class UopzTestClass {
    public function getValue() {
        return "reloaded";
    }
}
');

        // Trigger reload
        $reloader->onInput($shell, 'test');

        // Verify reload worked
        $this->assertEquals('reloaded', $obj->getValue());

        // Verify new instances also get reloaded code
        $obj2 = new \UopzTestClass();
        $this->assertEquals('reloaded', $obj2->getValue());
    }

    public function testReloadMethodWithThisBinding()
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'psysh_this_test_');
        $this->tempFiles[] = $testFile;

        // Create class with method that uses $this to access private property
        \file_put_contents($testFile, '<?php
class UopzThisBindingTest {
    private string $name = "initial";

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }
}
');

        require $testFile;

        $obj = new \UopzThisBindingTest();
        $this->assertEquals('initial', $obj->getName());

        // Set a value to verify instance state is preserved
        $obj->setName('modified');
        $this->assertEquals('modified', $obj->getName());

        $reloader = new UopzReloader();
        $shell = $this->getShell();
        $reloader->onInput($shell, 'test');

        // Modify methods to use $this differently
        $this->writeFileForReload($testFile, '<?php
class UopzThisBindingTest {
    private string $name = "initial";

    public function getName(): string {
        return "reloaded: " . $this->name;
    }

    public function setName(string $name): void {
        $this->name = strtoupper($name);
    }
}
');

        $reloader->onInput($shell, 'test');

        // Verify $this->name still has instance value, but method behavior changed
        $this->assertEquals('reloaded: modified', $obj->getName());

        // Verify setName now uppercases
        $obj->setName('test');
        $this->assertEquals('reloaded: TEST', $obj->getName());

        // Verify new instances also work correctly with $this
        $obj2 = new \UopzThisBindingTest();
        $this->assertEquals('reloaded: initial', $obj2->getName());
    }

    public function testReloadMethodPreservesReturnType()
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'psysh_return_test_');
        $this->tempFiles[] = $testFile;

        \file_put_contents($testFile, '<?php
class UopzReturnTypeTest {
    public function getInt(): int {
        return 1;
    }

    public function getNullableString(): ?string {
        return "hello";
    }
}
');

        require $testFile;

        $obj = new \UopzReturnTypeTest();
        $this->assertSame(1, $obj->getInt());
        $this->assertSame('hello', $obj->getNullableString());

        $reloader = new UopzReloader();
        $shell = $this->getShell();
        $reloader->onInput($shell, 'test');

        $this->writeFileForReload($testFile, '<?php
class UopzReturnTypeTest {
    public function getInt(): int {
        return 42;
    }

    public function getNullableString(): ?string {
        return null;
    }
}
');

        $reloader->onInput($shell, 'test');

        // Return types should be preserved and values updated
        $this->assertSame(42, $obj->getInt());
        $this->assertNull($obj->getNullableString());
    }

    public function testReloadClassConstant()
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'psysh_const_test_');
        $this->tempFiles[] = $testFile;

        \file_put_contents($testFile, '<?php
class UopzConstTest {
    const VERSION = 1;
}
');

        require $testFile;

        $this->assertEquals(1, \UopzConstTest::VERSION);

        $reloader = new UopzReloader();
        $shell = $this->getShell();
        $reloader->onInput($shell, 'test');

        $this->writeFileForReload($testFile, '<?php
class UopzConstTest {
    const VERSION = 999;
}
');

        $reloader->onInput($shell, 'test');

        $this->assertEquals(999, \UopzConstTest::VERSION);
    }

    public function testReloadFunction()
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'psysh_func_test_');
        $this->tempFiles[] = $testFile;

        \file_put_contents($testFile, '<?php
function uopzTestFunction() {
    return "original";
}
');

        require $testFile;

        $this->assertEquals('original', \uopzTestFunction());

        $reloader = new UopzReloader();
        $shell = $this->getShell();
        $reloader->onInput($shell, 'test');

        $this->writeFileForReload($testFile, '<?php
function uopzTestFunction() {
    return "reloaded";
}
');

        $reloader->onInput($shell, 'test');

        $this->assertEquals('reloaded', \uopzTestFunction());
    }

    public function testAddNewFunction()
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'psysh_newfunc_test_');
        $this->tempFiles[] = $testFile;

        // Start with a file that has no function
        \file_put_contents($testFile, '<?php
// placeholder
');

        require $testFile;

        $this->assertFalse(\function_exists('uopzNewFunction'));

        $reloader = new UopzReloader();
        $shell = $this->getShell();
        $reloader->onInput($shell, 'test');

        // Add a new function
        $this->writeFileForReload($testFile, '<?php
function uopzNewFunction() {
    return "i am new";
}
');

        $reloader->onInput($shell, 'test');

        $this->assertTrue(\function_exists('uopzNewFunction'));
        $this->assertEquals('i am new', \uopzNewFunction());
    }

    public function testSkipsInvalidModifiedFiles()
    {
        \file_put_contents($this->testFile, '<?php
class UopzSkipTest {
    public function getValue() {
        return "valid";
    }
}
');

        require $this->testFile;

        $obj = new \UopzSkipTest();
        $this->assertEquals('valid', $obj->getValue());

        $reloader = new UopzReloader();
        $shell = $this->getShell();
        $reloader->onInput($shell, 'test');

        // Write invalid PHP with future timestamp
        $this->writeFileForReload($this->testFile, '<?php this is not valid PHP');

        // This should not throw, just skip the file
        // We suppress output since shell->writeException requires initialized output
        \ob_start();
        try {
            $reloader->onInput($shell, 'test');
        } finally {
            \ob_end_clean();
        }

        // Original code should still work (not reloaded due to parse error)
        $this->assertEquals('valid', $obj->getValue());
    }

    public function testReloadWithNamespace()
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'psysh_ns_test_');
        $this->tempFiles[] = $testFile;

        \file_put_contents($testFile, '<?php
namespace UopzTestNs;

class NsClass {
    public function getValue() {
        return "ns original";
    }
}

function nsFunction() {
    return "ns func original";
}

const NS_CONST = 100;
');

        require $testFile;

        $obj = new \UopzTestNs\NsClass();
        $this->assertEquals('ns original', $obj->getValue());
        $this->assertEquals('ns func original', \UopzTestNs\nsFunction());
        $this->assertEquals(100, \UopzTestNs\NS_CONST);

        $reloader = new UopzReloader();
        $shell = $this->getShell();
        $reloader->onInput($shell, 'test');

        $this->writeFileForReload($testFile, '<?php
namespace UopzTestNs;

class NsClass {
    public function getValue() {
        return "ns reloaded";
    }
}

function nsFunction() {
    return "ns func reloaded";
}

const NS_CONST = 777;
');

        $reloader->onInput($shell, 'test');

        $this->assertEquals('ns reloaded', $obj->getValue());
        $this->assertEquals('ns func reloaded', \UopzTestNs\nsFunction());
        $this->assertEquals(777, \UopzTestNs\NS_CONST);
    }

    private function getShell(): Shell
    {
        $config = new \Psy\Configuration([
            'configFile' => __DIR__.'/../fixtures/empty.php',
        ]);

        return new Shell($config);
    }

    /**
     * Write file contents and set a future mtime to trigger reload detection.
     *
     * This avoids using sleep(1) which makes tests slow and potentially flaky.
     */
    private function writeFileForReload(string $file, string $contents): void
    {
        \file_put_contents($file, $contents);
        \touch($file, \time() + 1);
    }
}
