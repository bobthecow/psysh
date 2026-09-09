<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Configuration;
use Psy\Exception\InvalidManualException;
use Psy\Formatter\SignatureFormatter;
use Psy\Manual\V3Manual;

class ConfigurationManualTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = TempPaths::directory('psysh-test-configuration-manual-');
    }

    protected function tearDown(): void
    {
        SignatureFormatter::setManual(null);
        parent::tearDown();
    }

    public function testReloadManualReplacesCachedV3Manual(): void
    {
        $manualFile = $this->tempDir.'/php_manual.php';
        $this->writeManual($manualFile, '3.0.0', 'Old documentation');

        $config = $this->createConfiguration();
        $manual = $config->getManual();

        $this->assertInstanceOf(V3Manual::class, $manual);
        $this->assertSame('Old documentation', $manual->get('test'));

        $this->writeManual($manualFile, '3.1.0', 'Updated documentation');
        $this->assertTrue($config->reloadManual($manualFile));
        $updated = $config->getManual();

        $this->assertInstanceOf(V3Manual::class, $updated);
        $this->assertNotSame($manual, $updated);
        $this->assertSame('Updated documentation', $updated->get('test'));
        $this->assertSame($updated, $config->getManual());
    }

    public function testReloadManualRediscoversPhpManualAfterLegacyPathWasCached(): void
    {
        $sqliteFile = $this->tempDir.'/php_manual.sqlite';
        \file_put_contents($sqliteFile, 'legacy manual placeholder');

        $config = $this->createConfiguration();
        $this->assertSame($sqliteFile, $config->getManualDbFile());

        $phpFile = $this->tempDir.'/php_manual.php';
        $this->writeManual($phpFile, '3.1.0', 'Current documentation');

        $this->assertTrue($config->reloadManual($phpFile));
        $manual = $config->getManual();

        $this->assertInstanceOf(V3Manual::class, $manual);
        $this->assertSame($phpFile, $config->getManualDbFile());
        $this->assertSame('Current documentation', $manual->get('test'));
    }

    public function testReloadManualKeepsConfiguredPathPinned(): void
    {
        $configuredFile = $this->tempDir.'/custom_manual.php';
        $this->writeManual($configuredFile, '3.0.0', 'Configured documentation');

        $config = $this->createConfiguration();
        $config->setManualDbFile($configuredFile);

        $discoveredFile = $this->tempDir.'/php_manual.php';
        $this->writeManual($discoveredFile, '3.1.0', 'Discovered documentation');

        $this->assertFalse($config->reloadManual($discoveredFile));
        $manual = $config->getManual();

        $this->assertInstanceOf(V3Manual::class, $manual);
        $this->assertSame($configuredFile, $config->getManualDbFile());
        $this->assertSame('Configured documentation', $manual->get('test'));
    }

    public function testReloadManualKeepsCurrentManualWhenReplacementIsInvalid(): void
    {
        $manualFile = $this->tempDir.'/php_manual.php';
        $this->writeManual($manualFile, '3.0.0', 'Working documentation');

        $config = $this->createConfiguration();
        $manual = $config->getManual();

        \file_put_contents($manualFile, '<?php return [\'invalid manual\'];');

        try {
            $config->reloadManual($manualFile);
            $this->fail('Expected invalid replacement manual to be rejected');
        } catch (InvalidManualException $e) {
            $this->assertSame($manualFile, $e->getManualFile());
        }

        $this->assertSame($manual, $config->getManual());
        $this->assertSame('Working documentation', $config->getManual()->get('test'));
    }

    public function testSetManualDbFileKeepsCurrentManualWhenReplacementIsInvalid(): void
    {
        $manualFile = $this->tempDir.'/working_manual.php';
        $this->writeManual($manualFile, '3.0.0', 'Working documentation');

        $config = $this->createConfiguration();
        $config->setManualDbFile($manualFile);
        $manual = $config->getManual();

        $invalidFile = $this->tempDir.'/invalid_manual.php';
        \file_put_contents($invalidFile, '<?php return [\'invalid manual\'];');

        try {
            $config->setManualDbFile($invalidFile);
            $this->fail('Expected invalid configured manual to be rejected');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(InvalidManualException::class, $e->getPrevious());
        }

        $this->assertSame($manualFile, $config->getManualDbFile());
        $this->assertSame($manual, $config->getManual());
        $this->assertSame('Working documentation', $config->getManual()->get('test'));
    }

    public function testSetManualDbFileKeepsCurrentManualWhenLoadingThrows(): void
    {
        $manualFile = $this->tempDir.'/working_manual.php';
        $this->writeManual($manualFile, '3.0.0', 'Working documentation');

        $config = $this->createConfiguration();
        $config->setManualDbFile($manualFile);
        $manual = $config->getManual();

        $throwingFile = $this->tempDir.'/throwing_manual.php';
        \file_put_contents($throwingFile, '<?php throw new \RuntimeException(\'Manual load failed\');');

        try {
            $config->setManualDbFile($throwingFile);
            $this->fail('Expected configured manual loading failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Manual load failed', $e->getMessage());
        }

        $this->assertSame($manualFile, $config->getManualDbFile());
        $this->assertSame($manual, $config->getManual());
    }

    private function createConfiguration(): Configuration
    {
        return new Configuration([
            'configFile'   => __DIR__.'/Fixtures/empty.php',
            'dataDir'      => $this->tempDir,
            'trustProject' => false,
        ]);
    }

    private function writeManual(string $filePath, string $version, string $doc): void
    {
        $meta = \var_export(['version' => $version], true);
        $doc = \var_export($doc, true);
        $content = '<?php
return new class {
    public function get(string $id) {
        return $id === \'test\' ? '.$doc.' : null;
    }

    public function getMeta(): array {
        return '.$meta.';
    }
};';

        \file_put_contents($filePath, $content);
    }
}
