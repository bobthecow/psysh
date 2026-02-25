<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion;

use Psy\Completion\CompletionKind;
use Psy\Completion\ContextAnalyzer;
use Psy\Test\TestCase;

class ContextAnalyzerTest extends TestCase
{
    private ContextAnalyzer $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new ContextAnalyzer();
    }

    /**
     * @dataProvider variableContextProvider
     */
    public function testVariableContext(string $input, int $cursor, string $expectedPrefix)
    {
        $result = $this->analyzer->analyze($input, $cursor);

        $this->assertEquals(CompletionKind::VARIABLE, $result->kinds);
        $this->assertEquals($expectedPrefix, $result->prefix);
        $this->assertNull($result->leftSide);
    }

    public function variableContextProvider(): array
    {
        return [
            // Basic variables
            ['$', 1, ''],
            ['$f', 2, 'f'],
            ['$foo', 4, 'foo'],
            ['$fooBar', 7, 'fooBar'],
            ['$foo_bar', 8, 'foo_bar'],

            // Variables with underscores
            ['$_', 2, '_'],
            ['$_foo', 5, '_foo'],
            ['$__', 3, '__'],

            // Simple variable completions (cursor at variable)
            ['$a + $b', 3, 'a'],
            // More complex cursor positions in the middle of expressions
            // are not fully supported yet

            // Variables in arrays (simple cases)
            ['[$foo', 5, 'foo'],
            ['[$a, $b', 7, 'b'],

            // Variables in function calls (simple cases)
            ['foo($bar', 8, 'bar'],
            ['foo($a, $b', 10, 'b'],
        ];
    }

    /**
     * @dataProvider objectMemberContextProvider
     */
    public function testObjectMemberContext(string $input, int $cursor, string $expectedPrefix, string $expectedLeftSide)
    {
        $result = $this->analyzer->analyze($input, $cursor);

        $this->assertEquals(CompletionKind::OBJECT_MEMBER, $result->kinds);
        $this->assertEquals($expectedPrefix, $result->prefix);
        $this->assertEquals($expectedLeftSide, $result->leftSide);
    }

    public function objectMemberContextProvider(): array
    {
        $cases = [
            // Basic object access
            ['$foo->', 6, '', '$foo'],
            ['$foo->bar', 10, 'bar', '$foo'],
            ['$foo->barBaz', 13, 'barBaz', '$foo'],
            ['$foo->bar_baz', 14, 'bar_baz', '$foo'],

            // Underscored variable names
            ['$foo_bar->', 10, '', '$foo_bar'],
            ['$foo_bar->baz', 14, 'baz', '$foo_bar'],
            ['$_foo->', 7, '', '$_foo'],
            ['$_foo->bar', 11, 'bar', '$_foo'],

            // Partial completions
            ['$obj->f', 8, 'f', '$obj'],
            ['$obj->for', 10, 'for', '$obj'],
            ['$obj->format', 13, 'format', '$obj'],
        ];

        if (\PHP_VERSION_ID >= 80000) {
            $cases[] = ['$user?->', 8, '', '$user'];
            $cases[] = ['$user?->name', 12, 'name', '$user'];
        }

        return $cases;
    }

    /**
     * @dataProvider staticMemberContextProvider
     */
    public function testStaticMemberContext(string $input, int $cursor, string $expectedPrefix, string $expectedLeftSide)
    {
        $result = $this->analyzer->analyze($input, $cursor);

        $this->assertEquals(CompletionKind::STATIC_MEMBER, $result->kinds);
        $this->assertEquals($expectedPrefix, $result->prefix);
        $this->assertEquals($expectedLeftSide, $result->leftSide);
    }

    public function staticMemberContextProvider(): array
    {
        return [
            // Basic static access
            ['Foo::', 5, '', 'Foo'],
            ['Foo::bar', 8, 'bar', 'Foo'],
            ['Foo::BAR', 8, 'BAR', 'Foo'],
            ['Foo::barBaz', 11, 'barBaz', 'Foo'],
            ['Foo::$bar', 9, 'bar', 'Foo'],
            ['Foo::$', 6, '', 'Foo'],

            // Namespaced classes
            ['DateTime::', 10, '', 'DateTime'],
            ['DateTime::createFromFormat', 26, 'createFromFormat', 'DateTime'],

            // With backslashes (note: php-parser preserves leading backslash in FullyQualified names)
            ['\\Foo::', 6, '', '\\Foo'],
            ['\\Foo\\Bar::', 10, '', '\\Foo\\Bar'],
            ['Foo\\Bar::baz', 13, 'baz', 'Foo\\Bar'],

            // Constants
            ['Foo::CONST', 10, 'CONST', 'Foo'],
            ['DateTime::ATOM', 14, 'ATOM', 'DateTime'],
        ];
    }

    /**
     * @dataProvider classNameContextProvider
     */
    public function testClassNameContext(string $input, int $cursor, string $expectedPrefix)
    {
        $result = $this->analyzer->analyze($input, $cursor);

        $this->assertEquals(CompletionKind::CLASS_NAME, $result->kinds);
        $this->assertEquals($expectedPrefix, $result->prefix);
    }

    public function classNameContextProvider(): array
    {
        return [
            // Basic new (note: 'new ' without class name is detected as partial)
            ['new D', 5, 'D'],
            ['new DateTime', 12, 'DateTime'],
            ['new DateTimeZone', 16, 'DateTimeZone'],

            // With namespaces
            ['new Foo\\Bar', 11, 'Foo\\Bar'],

            // Complex expressions with 'new' inside larger statements
            // are not fully supported yet; cursor must be at the class name
        ];
    }

    /**
     * @dataProvider functionNameContextProvider
     */
    public function testFunctionNameContext(string $input, int $cursor, string $expectedPrefix)
    {
        $result = $this->analyzer->analyze($input, $cursor);

        // Bare identifiers can be symbols, and may also include command context.
        $this->assertTrue(($result->kinds & CompletionKind::SYMBOL) !== 0, 'Expected SYMBOL kind to be set');
        $this->assertEquals($expectedPrefix, $result->prefix);
    }

    public function functionNameContextProvider(): array
    {
        return [
            // Basic functions
            ['str_', 4, 'str_'],
            ['str_replace', 11, 'str_replace'],
            ['array_map', 9, 'array_map'],

            // With namespaces (leading backslash stripped by Name->toString())
            ['strlen', 6, 'strlen'],
            ['Foo\\bar', 7, 'Foo\\bar'],
        ];
    }

    /**
     * @dataProvider commandContextProvider
     */
    public function testCommandContext(
        string $input,
        int $cursor,
        int $expectedKinds,
        string $expectedPrefix = '',
        ?string $expectedLeftSide = null
    ) {
        $result = $this->analyzer->analyze($input, $cursor);

        $this->assertEquals($expectedKinds, $result->kinds);
        $this->assertEquals($expectedPrefix, $result->prefix);
        $this->assertEquals($expectedLeftSide, $result->leftSide);
    }

    public function commandContextProvider(): array
    {
        $commandAndSymbol = CompletionKind::COMMAND | CompletionKind::KEYWORD | CompletionKind::SYMBOL;

        return [
            ['ls', 2, $commandAndSymbol, 'ls', null],
            ['doc', 3, $commandAndSymbol, 'doc', null],
            ['show', 4, $commandAndSymbol, 'show', null],
            ['ls -a', 5, CompletionKind::COMMAND_OPTION, '-a', 'ls'],
            ['show -v', 7, CompletionKind::COMMAND_OPTION, '-v', 'show'],
            ['help --ver', 10, CompletionKind::COMMAND_OPTION, '--ver', 'help'],
            ['help --dry-r', 12, CompletionKind::COMMAND_OPTION, '--dry-r', 'help'],
            ['ls --', 5, CompletionKind::COMMAND_OPTION, '--', 'ls'],
            ['ls ', 3, CompletionKind::UNKNOWN, '', null],
        ];
    }

    /**
     * @dataProvider edgeCasesProvider
     */
    public function testEdgeCases(string $input, int $cursor, int $expectedContext, string $expectedPrefix = '')
    {
        $result = $this->analyzer->analyze($input, $cursor);

        $this->assertEquals($expectedContext, $result->kinds);
        $this->assertEquals($expectedPrefix, $result->prefix);
    }

    public function edgeCasesProvider(): array
    {
        return [
            // Empty input
            ['', 0, CompletionKind::UNKNOWN],
            ['   ', 3, CompletionKind::UNKNOWN],

            // Just operators
            ['->', 2, CompletionKind::UNKNOWN],
            ['::', 2, CompletionKind::UNKNOWN],

            // Incomplete expressions
            ['$', 1, CompletionKind::VARIABLE],
            ['$foo->', 6, CompletionKind::OBJECT_MEMBER],
            ['Foo::', 5, CompletionKind::STATIC_MEMBER],

            // With whitespace
            ['$foo -> bar', 12, CompletionKind::OBJECT_MEMBER, 'bar'],
            ['Foo :: bar', 11, CompletionKind::STATIC_MEMBER, 'bar'],

            // Multiple statements (cursor in second)
            ['$a = 1; $b', 10, CompletionKind::VARIABLE, 'b'],

            // Word followed by space should not complete (user has moved past that word)
            ['doc ', 4, CompletionKind::UNKNOWN],
            ['show ', 5, CompletionKind::UNKNOWN],
            ['array ', 6, CompletionKind::UNKNOWN],
            ['strlen ', 7, CompletionKind::UNKNOWN],
        ];
    }

    /**
     * @dataProvider cursorPositionProvider
     */
    public function testCursorPosition(string $input, int $cursor, int $expectedContext, string $expectedPrefix)
    {
        $result = $this->analyzer->analyze($input, $cursor);

        $this->assertEquals($expectedContext, $result->kinds);
        $this->assertEquals($expectedPrefix, $result->prefix);
    }

    public function cursorPositionProvider(): array
    {
        return [
            // Cursor at different positions in '$foo'
            ['$foo', 1, CompletionKind::VARIABLE, ''],
            ['$foo', 2, CompletionKind::VARIABLE, 'f'],
            ['$foo', 3, CompletionKind::VARIABLE, 'fo'],
            ['$foo', 4, CompletionKind::VARIABLE, 'foo'],

            // Cursor in '$foo->bar'
            ['$foo->bar', 1, CompletionKind::VARIABLE, ''],
            ['$foo->bar', 4, CompletionKind::VARIABLE, 'foo'],
            // Cursor right after variable but before -> is tricky
            ['$foo->bar', 6, CompletionKind::OBJECT_MEMBER, ''],
            ['$foo->bar', 7, CompletionKind::OBJECT_MEMBER, 'b'],
            ['$foo->bar', 9, CompletionKind::OBJECT_MEMBER, 'bar'],

            // Cursor in 'new DateTime'
            ['new DateTime', 8, CompletionKind::CLASS_NAME, 'Date'],
            ['new DateTime', 12, CompletionKind::CLASS_NAME, 'DateTime'],
        ];
    }

    /**
     * @dataProvider unicodeCursorProvider
     */
    public function testUnicodeCursorPositions(string $input, int $cursor, int $expectedContext, string $expectedPrefix, ?string $expectedLeftSide = null)
    {
        $result = $this->analyzer->analyze($input, $cursor);

        $this->assertEquals($expectedContext, $result->kinds);
        $this->assertEquals($expectedPrefix, $result->prefix);
        if ($expectedLeftSide !== null) {
            $this->assertEquals($expectedLeftSide, $result->leftSide);
        }
    }

    /**
     * Non-ASCII regression tests.
     *
     * Cursor values are in code-point units (matching Buffer behavior).
     *
     * @return array[]
     */
    public function unicodeCursorProvider(): array
    {
        return [
            // Variable after multibyte characters
            'accented prefix' => [
                'é $foo', 6, CompletionKind::VARIABLE, 'foo',
            ],

            // Partial variable after multibyte
            'accented partial var' => [
                'é $fo', 5, CompletionKind::VARIABLE, 'fo',
            ],

            // Object member after multibyte
            'accented obj member' => [
                'é; $foo->bar', 12, CompletionKind::OBJECT_MEMBER, 'bar', '$foo',
            ],

            // Emoji before variable
            'emoji prefix' => [
                '👍 $foo', 6, CompletionKind::VARIABLE, 'foo',
            ],

            // CJK character before variable
            'CJK prefix' => [
                '你 $foo', 6, CompletionKind::VARIABLE, 'foo',
            ],

            // Combining character (é as e + combining acute)
            'combining char prefix' => [
                "e\u{0301} \$foo", 7, CompletionKind::VARIABLE, 'foo',
            ],

            // Cursor at end with trailing space after multibyte (should be UNKNOWN)
            'accented trailing space' => [
                'é ls ', 5, CompletionKind::UNKNOWN, '',
            ],

            // Emoji modifier sequence before variable
            'emoji modifier prefix' => [
                '👍🏽 $bar', 7, CompletionKind::VARIABLE, 'bar',
            ],
        ];
    }
}
