<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ValidConstructorPass;

class ValidConstructorPassTest extends CodeCleanerTestCase
{
    protected function setUp()
    {
        $this->setPass(new ValidConstructorPass());
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    /**
     * @dataProvider invalidParserStatements
     * @expectedException \Psy\Exception\ParseErrorException
     */
    public function testProcessInvalidStatementCatchedByParser($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        $stmts = [
            ['class A { public static function A() {}}'],
            ['class A { public static function a() {}}'],
            ['class A { private static function A() {}}'],
            ['class A { private static function a() {}}'],
        ];

        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $stmts[] = ['class A { public function A(): ?array {}}'];
            $stmts[] = ['class A { public function a(): ?array {}}'];
        }

        return $stmts;
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
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        $stmts = [
            ['class A { public static function A() {} public function __construct() {}}'],
            ['class A { private function __construct() {} public static function A() {}}'],
            ['namespace B; class A { private static function A() {}}'],
        ];

        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $stmts[] = ['class A { public static function A() {} public function __construct() {}}'];
            $stmts[] = ['class A { private function __construct() {} public static function A(): ?array {}}'];
            $stmts[] = ['namespace B; class A { private static function A(): ?array {}}'];
        }

        return $stmts;
    }
}
