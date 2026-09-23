<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PhpParser\Node\Expr\FuncCall;
use Psy\CodeCleaner\CalledClassPass;
use Psy\CodeCleaner\CallTimePassByReferencePass;
use Psy\CodeCleaner\FunctionReturnInWriteContextPass;
use Psy\CodeCleaner\PassableByReferencePass;
use Psy\Exception\FatalErrorException;

function partialReference($value, &$reference)
{
}

class PartialFunctionApplicationPassTest extends CodeCleanerTestCase
{
    protected function setUp(): void
    {
        if (!\method_exists(FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('Partial application requires PHP-Parser 5.9 or newer.');
        }
    }

    /**
     * @dataProvider validApplications
     */
    public function testValidApplication(string $passClass, string $code)
    {
        $this->setPass(new $passClass());
        $this->assertProcessesAs($code, $code);
    }

    public function validApplications(): array
    {
        return [
            [CallTimePassByReferencePass::class, 'strlen(?)'],
            [CallTimePassByReferencePass::class, '$object->method(?, $bound, ...)'],
            [CallTimePassByReferencePass::class, 'Example::method(bound: $bound, remaining: ?)'],
            [CallTimePassByReferencePass::class, '$closure(?, ...)'],
            [FunctionReturnInWriteContextPass::class, 'strlen(?)'],
            [FunctionReturnInWriteContextPass::class, '$object->method(?, $bound, ...)'],
            [FunctionReturnInWriteContextPass::class, 'Example::method(bound: $bound, remaining: ?)'],
            [FunctionReturnInWriteContextPass::class, '$closure(?, ...)'],
            [PassableByReferencePass::class, 'array_pop(?)'],
            [PassableByReferencePass::class, 'array_pop(array: ?)'],
            [PassableByReferencePass::class, 'preg_match(?, ?, $matches)'],
            [PassableByReferencePass::class, 'preg_match(matches: ?, pattern: "/x/", subject: ?)'],
            [PassableByReferencePass::class, '\\Psy\\Test\\CodeCleaner\\partialReference(reference: ?, value: 1)'],
            [PassableByReferencePass::class, 'array_multisort(...)'],
            [PassableByReferencePass::class, 'array_multisort(?)'],
            [PassableByReferencePass::class, 'array_multisort(?, SORT_ASC, SORT_NUMERIC, ?)'],
            [PassableByReferencePass::class, 'array_multisort($array, SORT_ASC, ...)'],
            [PassableByReferencePass::class, 'array_multisort([], ?)'],
            [PassableByReferencePass::class, 'array_multisort(42, ...)'],
            [PassableByReferencePass::class, 'array_multisort(?, SORT_ASC, SORT_NUMERIC, SORT_REGULAR, ...)'],
            [CalledClassPass::class, 'get_class(?)'],
            [CalledClassPass::class, 'get_class(object: ?)'],
            [CalledClassPass::class, 'get_class(null, ...)'],
            [CalledClassPass::class, 'get_called_class(...)'],
        ];
    }

    /**
     * @dataProvider invalidApplications
     */
    public function testInvalidBoundArgument(string $passClass, string $code)
    {
        $this->setPass(new $passClass());
        $this->expectException(FatalErrorException::class);
        $this->parseAndTraverse($code);
    }

    public function invalidApplications(): array
    {
        return [
            [CallTimePassByReferencePass::class, 'example(?, &$bound)'],
            [FunctionReturnInWriteContextPass::class, 'example(?, &value())'],
            [PassableByReferencePass::class, 'array_pop([], ...)'],
            [PassableByReferencePass::class, 'preg_match(?, ?, [])'],
            [PassableByReferencePass::class, '\\Psy\\Test\\CodeCleaner\\partialReference(value: ?, reference: [])'],
        ];
    }
}
