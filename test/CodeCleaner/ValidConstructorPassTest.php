<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ValidConstructorPass;

class ValidConstructorPassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new ValidConstructorPass());
    }

    /**
     * @dataProvider invalidStatements
     */
    public function testProcessInvalidStatement($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->parseAndTraverse($code);

        $this->fail();
    }

    /**
     * @dataProvider invalidParserStatements
     */
    public function testProcessInvalidStatementCatchedByParser($code)
    {
        $this->expectException(\Psy\Exception\ParseErrorException::class);
        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function invalidStatements()
    {
        return [
            ['class A { public static function A() {}}'],
            ['class A { public static function a() {}}'],
            ['class A { private static function A() {}}'],
            ['class A { private static function a() {}}'],
            ['class A { public function A(): ?array {}}'],
            ['class A { public function a(): ?array {}}'],
        ];
    }

    public function invalidParserStatements()
    {
        return [
            ['class A { public static function __construct() {}}'],
            ['class A { private static function __construct() {}}'],
            ['class A { private static function __construct() {} public function A() {}}'],
            ['namespace B; class A { private static function __construct() {}}'],
        ];
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessValidStatement($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return [
            ['class A { public static function A() {} public function __construct() {}}'],
            ['class A { private function __construct() {} public static function A() {}}'],
            ['namespace B; class A { private static function A() {}}'],
            ['class A { public static function A() {} public function __construct() {}}'],
            ['class A { private function __construct() {} public static function A(): ?array {}}'],
            ['namespace B; class A { private static function A(): ?array {}}'],
        ];
    }
}
