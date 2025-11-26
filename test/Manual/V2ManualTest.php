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

use Psy\Manual\V2Manual;
use Psy\Test\TestCase;

class V2ManualTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create the php_manual table
        $this->db->exec('CREATE TABLE php_manual (id TEXT PRIMARY KEY, doc TEXT)');

        // Create the meta table
        $this->db->exec('CREATE TABLE meta (id TEXT PRIMARY KEY, value TEXT)');

        // Insert some test data
        $this->db->exec("INSERT INTO php_manual (id, doc) VALUES ('strlen', 'Get string length')");
        $this->db->exec("INSERT INTO php_manual (id, doc) VALUES ('array_map', 'Apply callback to elements')");

        // Insert metadata
        $this->db->exec("INSERT INTO meta (id, value) VALUES ('version', '2.3.4')");
        $this->db->exec("INSERT INTO meta (id, value) VALUES ('language', 'en')");
        $this->db->exec("INSERT INTO meta (id, value) VALUES ('git_timestamp', '1700000000')");
        $this->db->exec("INSERT INTO meta (id, value) VALUES ('built_at', '1700000001')");
    }

    public function testGetVersion()
    {
        $manual = new V2Manual($this->db);
        $this->assertSame(2, $manual->getVersion());
    }

    public function testGetExistingDoc()
    {
        $manual = new V2Manual($this->db);

        $doc = $manual->get('strlen');
        $this->assertSame('Get string length', $doc);

        $doc = $manual->get('array_map');
        $this->assertSame('Apply callback to elements', $doc);
    }

    public function testGetNonexistentDoc()
    {
        $manual = new V2Manual($this->db);

        $doc = $manual->get('nonexistent_function_xyz');
        $this->assertNull($doc);
    }

    public function testGetMeta()
    {
        $manual = new V2Manual($this->db);
        $meta = $manual->getMeta();

        $this->assertArrayHasKey('format', $meta);
        $this->assertSame('sqlite', $meta['format']);

        $this->assertArrayHasKey('version', $meta);
        $this->assertSame('2.3.4', $meta['version']);

        $this->assertArrayHasKey('language', $meta);
        $this->assertSame('en', $meta['language']);

        // Numeric values should be converted to integers
        $this->assertArrayHasKey('git_timestamp', $meta);
        $this->assertSame(1700000000, $meta['git_timestamp']);

        $this->assertArrayHasKey('built_at', $meta);
        $this->assertSame(1700000001, $meta['built_at']);
    }

    public function testGetMetaWithEmptyTable()
    {
        // Create a fresh database without meta entries
        $db = new \PDO('sqlite::memory:');
        $db->exec('CREATE TABLE php_manual (id TEXT PRIMARY KEY, doc TEXT)');
        $db->exec('CREATE TABLE meta (id TEXT PRIMARY KEY, value TEXT)');

        $manual = new V2Manual($db);
        $meta = $manual->getMeta();

        // Should still have format key
        $this->assertArrayHasKey('format', $meta);
        $this->assertSame('sqlite', $meta['format']);

        // Should not have other keys
        $this->assertArrayNotHasKey('version', $meta);
    }

    public function testGetWithSpecialCharacters()
    {
        // Test SQL injection protection
        $db = new \PDO('sqlite::memory:');
        $db->exec('CREATE TABLE php_manual (id TEXT PRIMARY KEY, doc TEXT)');
        $db->exec('CREATE TABLE meta (id TEXT PRIMARY KEY, value TEXT)');
        $db->exec("INSERT INTO php_manual (id, doc) VALUES ('test::method', 'Method doc')");

        $manual = new V2Manual($db);

        // Should not cause SQL injection
        $doc = $manual->get("'; DROP TABLE php_manual; --");
        $this->assertNull($doc);

        $doc = $manual->get('test::method');
        $this->assertSame('Method doc', $doc);
    }
}
