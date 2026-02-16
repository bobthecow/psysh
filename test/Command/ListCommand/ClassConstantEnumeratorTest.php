<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand;

use Psy\Command\ListCommand\ClassConstantEnumerator;
use Psy\Test\Fixtures\Command\ListCommand\ClassAlfa;
use Psy\Test\Fixtures\Command\ListCommand\ClassBravo;
use Psy\Test\Fixtures\Command\ListCommand\ClassCharlie;
use Psy\Test\Fixtures\Command\ListCommand\InterfaceDelta;
use Psy\Test\Fixtures\Command\ListCommand\InterfaceEcho;
use Psy\Test\Fixtures\Command\ListCommand\TraitFoxtrot;
use Psy\Test\Fixtures\Command\ListCommand\TraitGolf;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * @group isolation-fail
 */
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
        $alfa = new ClassAlfa();
        $bravo = new ClassBravo();
        $charlie = new ClassCharlie();

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
            ['--constants', new \ReflectionClass(InterfaceDelta::class), null, [
                'Interface Constants' => [
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>dee</string>"'),
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass(InterfaceEcho::class), null, [
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
            ['--constants', new \ReflectionClass(TraitFoxtrot::class), null, []],
            ['--constants', new \ReflectionClass(TraitGolf::class), null, []],

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
            ['--constants --methods', new \ReflectionClass(InterfaceDelta::class), null, [
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

            ['--constants --no-inherit', new \ReflectionClass(InterfaceEcho::class), null, [
                'Interface Constants' => [
                    'E' => [
                        'name'  => 'E',
                        'style' => 'const',
                        'value' => OutputFormatter::escape('"<string>eee</string>"'),
                    ],
                ],
            ]],
        ];
    }
}
