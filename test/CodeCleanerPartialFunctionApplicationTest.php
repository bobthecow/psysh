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

use PhpParser\Node\Expr\FuncCall;
use PhpParser\ParserFactory;
use Psy\CodeCleaner;

class CodeCleanerPartialFunctionApplicationTest extends TestCase
{
    /**
     * @dataProvider partialApplications
     */
    public function testCleanPartialApplication(string $code, string $expected)
    {
        if (!\method_exists(FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('Partial application requires PHP-Parser 5.9 or newer.');
        }

        $cleaner = new CodeCleaner((new ParserFactory())->createForNewestSupportedVersion());
        $this->assertSame($expected, $cleaner->clean([$code]));
    }

    public function partialApplications(): array
    {
        return [
            ['strlen(?)', 'return strlen(?);'],
            ['str_replace("old", "new", ?)', 'return str_replace("old", "new", ?);'],
            ['str_replace(subject: ?, search: "old", replace: "new")', 'return str_replace(subject: ?, search: "old", replace: "new");'],
            ['array_map(strtoupper(?), ?)', 'return array_map(strtoupper(?), ?);'],
            ['array_pop(?)', 'return array_pop(?);'],
            ['array_multisort(?, SORT_ASC, ...)', 'return array_multisort(?, SORT_ASC, ...);'],
            ['$object->method(?, $bound)', 'return $object->method(?, $bound);'],
            ['Example::method(?, $bound)', 'return Example::method(?, $bound);'],
            ['$closure(?, ...)', 'return $closure(?, ...);'],
            ['"hello" |> str_replace("h", "H", ?)', 'return "hello" |> str_replace("h", "H", ?);'],
            ['strlen(...)', 'return strlen(...);'],
            ['array_multisort(...)', 'return array_multisort(...);'],
            ['get_class(null, ...)', 'return get_class(null, ...);'],
        ];
    }

    public function testPartialApplicationPreservesReferences()
    {
        if (\PHP_VERSION_ID < 80600 || !\method_exists(FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('Evaluating partial application requires PHP 8.6 and PHP-Parser 5.9.');
        }

        $cleaner = new CodeCleaner();
        $values = ['first', 'last'];
        $pop = eval($cleaner->clean(['array_pop(?)']));
        $this->assertSame('last', $pop($values));
        $this->assertSame(['first'], $values);

        $matches = [];
        $match = eval($cleaner->clean(['preg_match("/[a-z]+/", ?, $matches)']));
        $this->assertSame(1, $match('123word'));
        $this->assertSame(['word'], $matches);
    }

    public function testArrayMultisortPartialApplicationAcceptsBoundArray()
    {
        if (\PHP_VERSION_ID < 80600 || !\method_exists(FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('Evaluating partial application requires PHP 8.6 and PHP-Parser 5.9.');
        }

        $cleaner = new CodeCleaner();
        $sort = eval($cleaner->clean(['array_multisort([], ?)']));
        $this->assertTrue($sort(\SORT_ASC));
    }

    /**
     * @dataProvider executablePartialApplications
     */
    public function testEvaluatePartialApplication(string $code, string $expected)
    {
        if (\PHP_VERSION_ID < 80600 || !\method_exists(FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('Evaluating partial application requires PHP 8.6 and PHP-Parser 5.9.');
        }

        $cleaner = new CodeCleaner();
        $this->assertSame($expected, eval($cleaner->clean([$code])));
    }

    public function executablePartialApplications(): array
    {
        return [
            ['str_replace(subject: ?, search: ?, replace: "new")("old value", "old")', 'new value'],
            ['sprintf("%s:%s", ...)("first", "last")', 'first:last'],
            ['"hello" |> str_replace("h", "H", ?)', 'Hello'],
        ];
    }
}
