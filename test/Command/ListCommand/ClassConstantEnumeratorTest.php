<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand;

use Psy\Command\ListCommand\ClassConstantEnumerator;
use Symfony\Component\Console\Formatter\OutputFormatter;

class ClassConstantEnumeratorTest extends EnumeratorTestCase
{
    /**
     * @dataProvider enumerateInput
     */
    public function testEnumerate($inputStr, \Reflector $reflector, $target, $expectedItems)
    {
        $enumerator = new ClassConstantEnumerator($this->getPresenter());
        $input = $this->getInput($inputStr);

        $this->assertSame($expectedItems, $enumerator->enumerate($input, $reflector, $target));
    }

    public function enumerateInput()
    {
        $alfa = new Fixtures\ClassAlfa();
        $bravo = new Fixtures\ClassBravo();
        $charlie = new Fixtures\ClassCharlie();

        return [
            ['--constants', new \ReflectionClass($alfa), null, []],
            ['--constants', new \ReflectionClass($bravo), null, [
                'Class Constants' => [
                    'B' => [
                        'name'  => 'B',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>bee</string>"'),
                    ],
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>dee</string>"'),
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass($bravo), $bravo, [
                'Class Constants' => [
                    'B' => [
                        'name'  => 'B',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>bee</string>"'),
                    ],
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>dee</string>"'),
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass($charlie), $charlie, [
                'Class Constants' => [
                    'B' => [
                        'name'  => 'B',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>bee</string>"'),
                    ],
                    'C' => [
                        'name'  => 'C',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>cee</string>"'),
                    ],
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>dee</string>"'),
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass(Fixtures\InterfaceDelta::class), null, [
                'Interface Constants' => [
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>dee</string>"'),
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass(Fixtures\InterfaceEcho::class), null, [
                'Interface Constants' => [
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>dee</string>"'),
                    ],
                    'E' => [
                        'name'  => 'E',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>eee</string>"'),
                    ],
                ],
            ]],

            // Traits don't have constants
            ['--constants', new \ReflectionClass(Fixtures\TraitFoxtrot::class), null, []],
            ['--constants', new \ReflectionClass(Fixtures\TraitGolf::class), null, []],

            // If we didn't ask for 'em, don't include 'em
            ['', new \ReflectionClass($bravo), $bravo, []],
            ['', new \ReflectionClass($charlie), $charlie, []],

            // Include constants even when we ask for more things
            ['--constants --methods --classes', new \ReflectionClass($charlie), $charlie, [
                'Class Constants' => [
                    'B' => [
                        'name'  => 'B',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>bee</string>"'),
                    ],
                    'C' => [
                        'name'  => 'C',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>cee</string>"'),
                    ],
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>dee</string>"'),
                    ],
                ],
            ]],
            ['--constants --methods', new \ReflectionClass(Fixtures\InterfaceDelta::class), null, [
                'Interface Constants' => [
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>dee</string>"'),
                    ],
                ],
            ]],

            // Exclude inherited constants
            ['--constants --no-inherit', new \ReflectionClass($charlie), $charlie, [
                'Class Constants' => [
                    'C' => [
                        'name'  => 'C',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>cee</string>"'),
                    ],
                ],
            ]],

            // @todo move `testEnumerateExcludeInherited` into here after PHP 7.1
        ];
    }

    public function testEnumerateExcludeInherited()
    {
        // PHP < 7.1 does the wrong thing with interface constant inheritance, so let's skip for now
        if (\version_compare(\PHP_VERSION, '7.1', '<')) {
            return $this->markTestSkipped();
        }

        $enumerator = new ClassConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants --no-inherit');
        $reflector = new \ReflectionClass(Fixtures\InterfaceEcho::class);

        $this->assertSame([
            'Interface Constants' => [
                'E' => [
                    'name'  => 'E',
                    'style' => 'const',
                    'value' => OutputFormatter::escape('"<string>eee</string>"'),
                ],
            ],
        ], $enumerator->enumerate($input, $reflector));
    }
}
