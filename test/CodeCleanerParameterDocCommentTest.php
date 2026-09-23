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

use Psy\CodeCleaner;
use Psy\ParserFactory;

class CodeCleanerParameterDocCommentTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\method_exists((new ParserFactory())->createParser(), 'getTokens')) {
            $this->markTestSkipped('Parameter comment recovery requires PHP-Parser 5.');
        }
    }

    public function testPreservesCommentsMissingFromTheAst(): void
    {
        $code = (new CodeCleaner())->clean(['function (string /** middle */ $first, $second /** trailing */) {}']);

        $this->assertStringContainsString('string $first /** middle */', $code);
        $this->assertStringContainsString('$second /** trailing */', $code);
    }

    public function testPreservesCommentsInYoloMode(): void
    {
        $code = (new CodeCleaner(null, null, null, true))->clean(['function ($value /** trailing */) {}']);

        $this->assertStringContainsString('$value /** trailing */', $code);
    }

    public function testDoesNotDuplicateLeadingComments(): void
    {
        $code = (new CodeCleaner())->clean(['function (/** leading */ $value) {}']);

        $this->assertSame(1, \substr_count($code, '/** leading */'));
    }

    /**
     * @dataProvider parameterDocumentation
     */
    public function testPreservesReflectedDocumentation(string $code, array $expected): void
    {
        if (!\method_exists(\ReflectionParameter::class, 'getDocComment')) {
            $this->markTestSkipped('Parameter doc comments require PHP 8.6.');
        }

        $original = eval('return '.$code.';');
        $cleaned = eval((new CodeCleaner())->clean([$code]));

        $this->assertSame($expected, $this->parameterDocs($original));
        $this->assertSame($expected, $this->parameterDocs($cleaned));
    }

    public function parameterDocumentation(): array
    {
        return [
            'leading'                         => ['function (/** leading */ string $value) {}', ['/** leading */']],
            'middle'                          => ['function (string /** middle */ $value) {}', ['/** middle */']],
            'trailing'                        => ['function (string $value /** trailing */) {}', ['/** trailing */']],
            'last wins'                       => ['function (/** first */ string /** middle */ $value /** last */) {}', ['/** last */']],
            'ordinary comments'               => ['function ($value /** doc */ /* ordinary */) {}', ['/** doc */']],
            'separate parameters'             => ['function ($a /** first */, /** second */ $b, $c /** third */,) {}', ['/** first */', '/** second */', '/** third */']],
            'reference'                       => ['function (string & /** reference */ $value) {}', ['/** reference */']],
            'variadic'                        => ['function (string ...$values /** variadic */) {}', ['/** variadic */']],
            'default'                         => ['function ($value = /** default */ 1) {}', ['/** default */']],
            'array default'                   => ['function ($value = [/** array */ 1]) {}', ['/** array */']],
            'constructor argument'            => ['function ($value = new \ArrayObject(/** argument */ [])) {}', ['/** argument */']],
            'attribute ignores its docs'      => ['function (/** leading */ #[Unknown(/** attribute */ 1)] int $value) {}', ['/** leading */']],
            'after attribute'                 => ['function (#[Unknown] /** after */ int $value) {}', ['/** after */']],
            'closure consumes docs'           => ['function (/** closure */ $value = static function () {}) {}', [false]],
            'closure body docs stay isolated' => ['function ($value = static function () { /** body */ }) {}', [false]],
            'after closure'                   => ['function (/** closure */ $value = static function () {} /** parameter */) {}', ['/** parameter */']],
            'array containing closure'        => ['function ($value = [static function () {}, /** array */ 1]) {}', ['/** array */']],
            'arrow function'                  => ['fn (string $value /** arrow */) => $value', ['/** arrow */']],
        ];
    }

    public function testPreservesNestedClosureParameterDocumentation(): void
    {
        if (!\method_exists(\ReflectionParameter::class, 'getDocComment')) {
            $this->markTestSkipped('Parameter doc comments require PHP 8.6.');
        }

        $code = 'function ($value = static function ($nested /** nested */) {} /** outer */) {}';
        $function = eval((new CodeCleaner())->clean([$code]));
        $parameter = (new \ReflectionFunction($function))->getParameters()[0];

        $this->assertSame('/** outer */', $parameter->getDocComment());
        $this->assertSame(['/** nested */'], $this->parameterDocs($parameter->getDefaultValue()));
    }

    /**
     * @dataProvider promotedParameterDocumentation
     */
    public function testPreservesPromotedParameterDocumentation(string $code, array $expected): void
    {
        if (!\method_exists(\ReflectionParameter::class, 'getDocComment')) {
            $this->markTestSkipped('Parameter doc comments require PHP 8.6.');
        }

        $original = eval('return '.$code.';');
        $cleaned = eval((new CodeCleaner())->clean([$code]));
        foreach ([$original, $cleaned] as $object) {
            $constructor = new \ReflectionMethod($object, '__construct');
            $property = new \ReflectionProperty($object, 'value');

            $this->assertSame($expected[0], $constructor->getParameters()[0]->getDocComment());
            $this->assertSame($expected[0], $property->getDocComment());
            if (isset($expected[1])) {
                $this->assertSame($expected[1], $property->getHook(\PropertyHookType::Get)->getDocComment());
            }
        }
    }

    public function promotedParameterDocumentation(): array
    {
        return [
            'ordinary promotion'          => ['new class { public function __construct(public int $value = 1 /** value */) {} }', ['/** value */']],
            'before hooks'                => ['new class { public function __construct(public int $value = 1 /** value */ { get => $this->value; }) {} }', ['/** value */', false]],
            'leading before hooks'        => ['new class { public function __construct(/** value */ public int $value = 1 { get => $this->value; }) {} }', ['/** value */', false]],
            'hook docs stay isolated'     => ['new class { public function __construct(public int $value = 1 { /** hook */ get => $this->value; }) {} }', [false, '/** hook */']],
            'docs inside and after hooks' => ['new class { public function __construct(public int $value = 1 /** value */ { /** hook */ get { /** body */ return $this->value; } } /** after */) {} }', ['/** value */', '/** hook */']],
        ];
    }

    private function parameterDocs(\Closure $function): array
    {
        return \array_map(function (\ReflectionParameter $parameter) {
            return $parameter->getDocComment();
        }, (new \ReflectionFunction($function))->getParameters());
    }
}
