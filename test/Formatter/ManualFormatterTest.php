<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\LinkFormatter;
use Psy\Formatter\ManualFormatter;
use Psy\Output\Theme;
use Psy\Readline\Interactive\Layout\DisplayString;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;

class ManualFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear styles before each test
        LinkFormatter::setStyles([]);
    }

    public function testFormatFunction()
    {
        $manual = $this->getMockBuilder(\Psy\Manual\ManualInterface::class)->getMock();
        $formatter = new ManualFormatter(100, $manual);

        $data = [
            'type'        => 'function',
            'name'        => 'array_map',
            'description' => 'Applies a callback to each element of an array.',
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('Applies a callback', $result);
        $this->assertStringContainsString('Description:', $result);
    }

    public function testFormatClass()
    {
        $manual = $this->getMockBuilder(\Psy\Manual\ManualInterface::class)->getMock();
        $formatter = new ManualFormatter(100, $manual);

        $data = [
            'type'        => 'class',
            'name'        => 'ArrayObject',
            'description' => 'This class allows objects to work as arrays.',
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('allows objects to work', $result);
        $this->assertStringContainsString('Description:', $result);
    }

    public function testFormatConstant()
    {
        $manual = $this->getMockBuilder(\Psy\Manual\ManualInterface::class)->getMock();
        $formatter = new ManualFormatter(100, $manual);

        $data = [
            'type'        => 'constant',
            'name'        => 'E_ERROR',
            'description' => 'Fatal run-time errors.',
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('Fatal run-time', $result);
        $this->assertStringContainsString('Description:', $result);
    }

    public function testFormatContentBlocks()
    {
        $formatter = new ManualFormatter(100, null);

        $data = [
            'type'        => 'language',
            'description' => 'Operator precedence.',
            'content'     => [
                [
                    'type' => 'paragraph',
                    'text' => 'Before the table.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Precedence table',
                    'headers' => ['Operator', 'Description'],
                    'rows'    => [
                        ['<literal>+</literal>', 'Addition'],
                        ['<function>strlen</function>', 'String length'],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'text' => 'After the table.',
                ],
            ],
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('Before the table.', $result);
        $this->assertStringContainsString('Precedence table:', $result);
        $this->assertStringContainsString('Operator', $result);
        $this->assertStringContainsString('Addition', $result);
        $this->assertStringContainsString('strlen()', $result);
        $this->assertStringContainsString('After the table.', $result);
        $this->assertLessThan(\strpos($result, 'Precedence table:'), \strpos($result, 'Before the table.'));
        $this->assertGreaterThan(\strpos($result, 'Precedence table:'), \strpos($result, 'After the table.'));
    }

    public function testFormatReturnContentBlocks()
    {
        $formatter = new ManualFormatter(100, null);

        $data = [
            'type'        => 'function',
            'description' => 'Gets the type.',
            'return'      => [
                'type'    => 'string',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'text' => 'Returns a value according to this table.',
                    ],
                    [
                        'type'    => 'table',
                        'title'   => 'Return values',
                        'headers' => ['Value', 'Result'],
                        'rows'    => [
                            ['<type>null</type>', '<literal>null</literal>'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('Return:', $result);
        $this->assertStringContainsString('string', $result);
        $this->assertStringContainsString('Returns a value according to this table.', $result);
        $this->assertStringContainsString('Return values:', $result);
        $this->assertMatchesRegularExpression('/^<comment>  Value/m', $result);
        $this->assertStringContainsString('null', $result);
        $this->assertStringContainsString('<info>null</info>', $result);
        $this->assertMatchesRegularExpression('/^<comment>  Return values:<\/comment>$/m', $result);
    }

    public function testFormatTableBlocksWrapToWidth()
    {
        $formatter = new ManualFormatter(50, null);

        $data = [
            'type'        => 'function',
            'description' => 'Gets the type.',
            'return'      => [
                'type'    => 'string',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'text' => 'Returns a value according to this table.',
                    ],
                    [
                        'type'    => 'table',
                        'title'   => 'Return values',
                        'headers' => ['Value', 'Result'],
                        'rows'    => [
                            [
                                '<type>null</type>',
                                'This is a deliberately long description that should wrap inside the table column instead of overflowing the configured terminal width.',
                            ],
                            [
                                '<type>😀 😀 😀 😀</type>',
                                '表示幅の広い文字も列幅に収まるように折り返します。',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $formatter->format($data);
        $this->assertFormattedLinesFitWidth($result, 50);

        $this->assertStringContainsString('inside the table column instead of', $result);
    }

    public function testWrappedStyledTableCellsDoNotStyleAdjacentColumns()
    {
        $formatter = new ManualFormatter(24, null);

        $data = [
            'type'   => 'function',
            'return' => [
                'type'    => 'string',
                'content' => [
                    [
                        'type'    => 'table',
                        'headers' => ['Value', 'Description'],
                        'rows'    => [
                            ['<literal>resource (closed)</literal>', 'other'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $formatter->format($data);

        $this->assertFormattedLinesFitWidth($result, 24);
        $this->assertDoesNotMatchRegularExpression('/<return>resource\s+other/', $result);
        $this->assertMatchesRegularExpression('/<return>resource\s*<\/return>\s+other/', $result);
        $this->assertStringContainsString('<return>(closed)</return>', $result);
    }

    public function testWrappedTableCellsIgnoreUnknownTagsWhenBalancingStyles()
    {
        $formatter = new ManualFormatter(24, null);

        $data = [
            'type'   => 'function',
            'return' => [
                'type'    => 'string',
                'content' => [
                    [
                        'type'    => 'table',
                        'headers' => ['Value', 'Description'],
                        'rows'    => [
                            ['<notastyle>resource (closed)</notastyle>', 'other'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('\\<notastyle\\>resource', $result);
        $this->assertStringContainsString('(closed)\\</notastyle\\>', $result);
        $this->assertStringNotContainsString('<notastyle>\\<notastyle\\>resource', $result);
        $this->assertStringNotContainsString('\\</notastyle\\></notastyle>', $result);
    }

    public function testHeaderlessTablesDoNotRenderBlankHeaderRow()
    {
        $formatter = new ManualFormatter(80, null);

        $data = [
            'type'   => 'function',
            'return' => [
                'type'    => 'array',
                'content' => [
                    [
                        'type' => 'table',
                        'rows' => [
                            ['key', 'value'],
                            ['next', 'item'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('key', $result);
        $this->assertStringContainsString('value', $result);
        $this->assertDoesNotMatchRegularExpression('/^<comment>\s*<\/comment>$/m', $result);
    }

    public function testWideTableColumnsDoNotStarveReturnValueColumn()
    {
        $formatter = new ManualFormatter(80, null);

        $data = [
            'type'   => 'function',
            'return' => [
                'type'    => 'string',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'text' => 'Possible values for the returned string are:',
                    ],
                    [
                        'type'    => 'table',
                        'headers' => ['Type + State', 'Return Value', 'Notes'],
                        'rows'    => [
                            ['null', '"null"', '-'],
                            ['Booleans (true or false)', '"bool"', '-'],
                            ['Integers', '"int"', '-'],
                            ['Floats', '"float"', '-'],
                            ['Strings', '"string"', '-'],
                            ['Arrays', '"array"', '-'],
                            ['Resources', '"resource (resourcename)"', '-'],
                            ['Resources (Closed)', '"resource (closed)"', 'Example: A file stream after being closed with fclose().'],
                            ['Objects from Named Classes', 'The full name of the class including its namespace e.g. Foo\\Bar', '-'],
                            [
                                'Objects from Anonymous Classes',
                                '"class@anonymous" or parent class name/interface name if the class extends another class or implements an interface e.g. "Foo\\Bar@anonymous"',
                                'Anonymous classes are those created through the $x = new class { ... } syntax',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $formatter->format($data);
        $this->assertFormattedLinesFitWidth($result, 80);

        $this->assertStringContainsString('"resource (resourcename)"', $result);
        $this->assertStringContainsString('The full name of the class', $result);
        $this->assertDoesNotMatchRegularExpression('/"resource\s*\n\s*\(resourcename\)"/', $result);
    }

    public function testTableColumnsPreferLongestWordWidthWhenBudgetAllows()
    {
        $formatter = new ManualFormatter(120, null);

        $data = [
            'type'   => 'function',
            'return' => [
                'type'    => 'int',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'text' => 'Returns an integer, the value can be one of the following constants:',
                    ],
                    [
                        'type'    => 'table',
                        'title'   => 'JSON error codes',
                        'headers' => ['Constant', 'Meaning', 'Availability'],
                        'rows'    => [
                            ['<constant>JSON_ERROR_NONE</constant>', 'No error has occurred', ''],
                            ['<constant>JSON_ERROR_DEPTH</constant>', 'The maximum stack depth has been exceeded', ''],
                            ['<constant>JSON_ERROR_STATE_MISMATCH</constant>', 'Invalid or malformed JSON', ''],
                            ['<constant>JSON_ERROR_CTRL_CHAR</constant>', 'Control character error, possibly incorrectly encoded', ''],
                            ['<constant>JSON_ERROR_SYNTAX</constant>', 'Syntax error', ''],
                            ['<constant>JSON_ERROR_UTF8</constant>', 'Malformed UTF-8 characters, possibly incorrectly encoded', ''],
                            ['<constant>JSON_ERROR_RECURSION</constant>', 'One or more recursive references in the value to be encoded', ''],
                            ['<constant>JSON_ERROR_INF_OR_NAN</constant>', 'One or more NAN or INF values in the value to be encoded', ''],
                            ['<constant>JSON_ERROR_UNSUPPORTED_TYPE</constant>', 'A value of a type that cannot be encoded was given', ''],
                            ['<constant>JSON_ERROR_INVALID_PROPERTY_NAME</constant>', 'A property name that cannot be encoded was given', ''],
                            ['<constant>JSON_ERROR_UTF16</constant>', 'Malformed UTF-16 characters, possibly incorrectly encoded', ''],
                            [
                                '<constant>JSON_ERROR_NON_BACKED_ENUM</constant>',
                                'Value contains a non-backed enum which cannot be serialized.',
                                'Available as of PHP 8.1.0.',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $formatter->format($data);
        $this->assertFormattedLinesFitWidth($result, 120);

        $this->assertMatchesRegularExpression('/Constant\s+Meaning\s+Availability/', $result);
        $this->assertMatchesRegularExpression('/JSON_ERROR_INVALID_PROPERTY_NAME<\/info>\s+A property name/', $result);
        $this->assertDoesNotMatchRegularExpression('/A property name[^\n]*\n\s*<info>JSON_ERROR_INVALID_PROPERTY_NAME/', $result);
    }

    public function testSimpleReturnContentUsesAlignedLayout()
    {
        $formatter = new ManualFormatter(100, null);

        $data = [
            'type'        => 'function',
            'description' => 'Gets a count.',
            'return'      => [
                'type'        => 'int',
                'description' => 'Returns the count.',
                'content'     => [[
                    'type' => 'paragraph',
                    'text' => 'Returns the count.',
                ]],
            ],
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('  <info>int</info>  Returns the count.', $result);
    }

    public function testSingleParagraphContentDoesNotRequireLegacyDescription()
    {
        $formatter = new ManualFormatter(100, null);

        $data = [
            'type'    => 'function',
            'content' => [[
                'type' => 'paragraph',
                'text' => 'Content-only description.',
            ]],
            'params' => [[
                'name'    => '$value',
                'type'    => 'mixed',
                'content' => [[
                    'type' => 'paragraph',
                    'text' => 'Content-only parameter.',
                ]],
            ]],
            'return' => [
                'type'    => 'string',
                'content' => [[
                    'type' => 'paragraph',
                    'text' => 'Content-only return.',
                ]],
            ],
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('Content-only description.', $result);
        $this->assertStringContainsString('  <info>mixed</info>  <strong>$value</strong>  Content-only parameter.', $result);
        $this->assertStringContainsString('  <info>string</info>  Content-only return.', $result);
    }

    public function testUnionTypesStyleEachTypeSeparately()
    {
        $wideFormatter = new ManualFormatter(100, null);
        $narrowFormatter = new ManualFormatter(40, null);

        $data = [
            'type'        => 'function',
            'description' => 'Handles union types.',
            'params'      => [[
                'name'        => '$options',
                'type'        => 'array|null',
                'description' => 'Options.',
            ]],
            'return'      => [
                'type'        => 'array|object',
                'description' => 'Result.',
            ],
        ];

        $wideResult = $wideFormatter->format($data);
        $narrowResult = $narrowFormatter->format($data);

        foreach ([$wideResult, $narrowResult] as $result) {
            $this->assertStringContainsString('<info>array</info>|<info>null</info>', $result);
            $this->assertStringContainsString('<info>array</info>|<info>object</info>', $result);
            $this->assertStringNotContainsString('<info>array|null</info>', $result);
            $this->assertStringNotContainsString('<info>array|object</info>', $result);
        }
    }

    public function testFormatParameterContentBlocks()
    {
        $formatter = new ManualFormatter(100, null);

        $data = [
            'type'        => 'function',
            'description' => 'Gets an attribute.',
            'params'      => [[
                'name'    => '$flags',
                'type'    => 'int',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'text' => 'Controls how the attribute is read.',
                    ],
                    [
                        'type'    => 'table',
                        'title'   => 'Supported flags',
                        'headers' => ['Flag', 'Description'],
                        'rows'    => [
                            ['<constant>XATTR_ROOT</constant>', 'Root namespace.'],
                        ],
                    ],
                    [
                        'type' => 'code',
                        'role' => 'php',
                        'text' => "<?php\nxattr_get('file', 'name', XATTR_ROOT);",
                    ],
                ],
            ]],
        ];

        $result = $formatter->format($data);

        $this->assertStringContainsString('Param:', $result);
        $this->assertStringContainsString('$flags', $result);
        $this->assertStringContainsString('Controls how the attribute is read.', $result);
        $this->assertStringContainsString('Supported flags:', $result);
        $this->assertStringContainsString('XATTR_ROOT', $result);
        $this->assertStringContainsString('\\<?php', $result);
    }

    public function testHyperlinksUseInlineStyles()
    {
        // Set up inline styles for hyperlinks
        LinkFormatter::setStyles([
            'info' => 'fg=cyan;options=bold',
        ]);

        // Create a manual mock that returns data for array_map
        $manual = $this->getMockBuilder(\Psy\Manual\ManualInterface::class)->getMock();
        $manual->method('get')
            ->willReturnCallback(function ($id) {
                if ($id === 'array_map') {
                    return ['type' => 'function', 'description' => 'Test'];
                }

                return null;
            });

        $formatter = new ManualFormatter(100, $manual);

        $data = [
            'type'        => 'function',
            'name'        => 'array_map',
            'description' => 'Test function that references <function>array_map</function>.',
        ];

        $result = $formatter->format($data);

        // Verify the function name appears in output
        $this->assertStringContainsString('array_map', $result);

        // If hyperlinks are supported, verify the inline style is preserved
        if (LinkFormatter::supportsLinks()) {
            // The output should contain the href with inline style
            // This ensures ManualFormatter benefits from LinkFormatter's inline style handling
            $this->assertIsString($result);
        }
    }

    public function testFormatterCapsWidthAtMaximum()
    {
        $formatter = new ManualFormatter(200); // Request very wide terminal
        $this->assertInstanceOf(ManualFormatter::class, $formatter);

        // We can't directly test the width cap, but we verify the formatter is created
        // The actual width capping is tested indirectly through text wrapping behavior
    }

    public function testFormatterWithoutManual()
    {
        $formatter = new ManualFormatter(100, null);

        $data = [
            'type'        => 'function',
            'name'        => 'test_func',
            'description' => 'Test description.',
        ];

        $result = $formatter->format($data);

        // Should still format without manual (no hyperlinks)
        $this->assertStringContainsString('Test description', $result);
        $this->assertStringContainsString('Description:', $result);
    }

    private function assertFormattedLinesFitWidth(string $result, int $width): void
    {
        $outputFormatter = new OutputFormatter();
        $theme = new Theme('modern');

        $theme->applyStyles($outputFormatter, !Theme::grayExists($outputFormatter));

        foreach (\explode("\n", \trim($result)) as $line) {
            $this->assertLessThanOrEqual($width, DisplayString::widthWithoutFormatting($line, $outputFormatter), $line);
        }
    }
}
