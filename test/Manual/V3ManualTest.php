<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Manual;

use Psy\Exception\InvalidManualException;
use Psy\Manual\V3Manual;
use Psy\Test\TestCase;

class V3ManualTest extends TestCase
{
    private $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = \sys_get_temp_dir().'/psysh_manual_test_'.\uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->tempDir)) {
            foreach (\glob($this->tempDir.'/*') as $file) {
                \unlink($file);
            }
            \rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testConstructorWithValidManual()
    {
        $filePath = $this->createManualFile([
            'version'  => '3.0',
            'language' => 'en',
        ]);

        $manual = new V3Manual($filePath);

        $this->assertSame(3, $manual->getVersion());
        $this->assertSame('Test documentation', $manual->get('test'));
        $meta = $manual->getMeta();
        $this->assertSame('3.0', $meta['version']);
        $this->assertSame('en', $meta['language']);
    }

    public function testConstructorRejectsNonObject()
    {
        $filePath = $this->tempDir.'/invalid_array.php';
        \file_put_contents($filePath, '<?php return ["not" => "an object"];');

        $this->expectException(InvalidManualException::class);
        $this->expectExceptionMessage('must return an object, got array');

        new V3Manual($filePath);
    }

    public function testConstructorRejectsInteger()
    {
        $filePath = $this->tempDir.'/invalid_integer.php';
        \file_put_contents($filePath, '<?php return 1;');

        $this->expectException(InvalidManualException::class);
        $this->expectExceptionMessage('must return an object, got integer');

        new V3Manual($filePath);
    }

    public function testConstructorRejectsCorruptedFile()
    {
        $filePath = $this->tempDir.'/corrupted.php';
        \file_put_contents($filePath, 'wat');

        $this->expectException(InvalidManualException::class);
        $this->expectExceptionMessage('must return an object, got integer');

        new V3Manual($filePath);
    }

    public function testConstructorRejectsMissingGetMethod()
    {
        $filePath = $this->tempDir.'/no_get.php';
        $code = '<?php
class ManualDataNoGet {
    public function getMeta(): array { return ["version" => "3.0"]; }
}
return new ManualDataNoGet();';
        \file_put_contents($filePath, $code);

        $this->expectException(InvalidManualException::class);
        $this->expectExceptionMessage('must have a get() method');

        new V3Manual($filePath);
    }

    public function testConstructorRejectsMissingGetMetaMethod()
    {
        $filePath = $this->tempDir.'/no_getmeta.php';
        $code = '<?php
class ManualDataNoGetMeta {
    public function get(string $id) { return null; }
}
return new ManualDataNoGetMeta();';
        \file_put_contents($filePath, $code);

        $this->expectException(InvalidManualException::class);
        $this->expectExceptionMessage('must have a getMeta() method');

        new V3Manual($filePath);
    }

    public function testConstructorRejectsWrongVersion()
    {
        $filePath = $this->createManualFile([
            'version'  => '2.0',
            'language' => 'en',
        ]);

        $this->expectException(InvalidManualException::class);
        $this->expectExceptionMessage('must be v3.x format, got version 2.0');

        new V3Manual($filePath);
    }

    public function testConstructorRejectsMissingVersion()
    {
        $filePath = $this->createManualFile([
            'language' => 'en',
        ]);

        $this->expectException(InvalidManualException::class);
        $this->expectExceptionMessage('must be v3.x format, got version unknown');

        new V3Manual($filePath);
    }

    public function testConstructorAcceptsV3PointReleases()
    {
        $filePath = $this->createManualFile([
            'version'  => '3.1.5',
            'language' => 'en',
        ]);

        $manual = new V3Manual($filePath);
        $meta = $manual->getMeta();
        $this->assertSame('3.1.5', $meta['version']);
    }

    /**
     * Create a valid v3 manual file for testing.
     *
     * @param array $meta Metadata to include
     *
     * @return string Path to the created file
     */
    private function createManualFile(array $meta): string
    {
        $filePath = $this->tempDir.'/manual_'.\uniqid().'.php';
        $metaExport = \var_export($meta, true);
        $className = 'ManualData'.\str_replace('.', '_', \uniqid('', true));
        $content = '<?php
class '.$className.' {
    private $meta;

    public function __construct() {
        $this->meta = '.$metaExport.';
    }

    public function get(string $id) {
        if ($id === \'test\') {
            return \'Test documentation\';
        }
        return null;
    }

    public function getMeta(): array {
        return $this->meta;
    }
}
return new '.$className.'();';

        \file_put_contents($filePath, $content);

        return $filePath;
    }
}
