<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2019 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command\ListCommand;

use Psy\Command\ListCommand\ClassConstantEnumerator;

class ClassConstantEnumeratorTest extends EnumeratorTestCase
{
    /**
     * @dataProvider enumerateInput
     */
    public function testEnumerate($inputStr, \Reflector $reflector, $target, $expectedItems)
    {
        $enumerator = new ClassConstantEnumerator($this->getPresenter());
        $input = $this->getInput($inputStr);

        $this->assertEquals($expectedItems, $enumerator->enumerate($input, $reflector, $target));
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
                        'value' => '"\<string>bee\</string>"',
                    ],
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => '"\<string>dee\</string>"',
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass($bravo), $bravo, [
                'Class Constants' => [
                    'B' => [
                        'name'  => 'B',
                        'style' => 'const',
                        'value' => '"\<string>bee\</string>"',
                    ],
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => '"\<string>dee\</string>"',
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass($charlie), $charlie, [
                'Class Constants' => [
                    'B' => [
                        'name'  => 'B',
                        'style' => 'const',
                        'value' => '"\<string>bee\</string>"',
                    ],
                    'C' => [
                        'name'  => 'C',
                        'style' => 'const',
                        'value' => '"\<string>cee\</string>"',
                    ],
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => '"\<string>dee\</string>"',
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta'), null, [
                'Interface Constants' => [
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => '"\<string>dee\</string>"',
                    ],
                ],
            ]],
            ['--constants', new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho'), null, [
                'Interface Constants' => [
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => '"\<string>dee\</string>"',
                    ],
                    'E' => [
                        'name'  => 'E',
                        'style' => 'const',
                        'value' => '"\<string>eee\</string>"',
                    ],
                ],
            ]],

            // Traits don't have constants
            ['--constants', new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot'), null, []],
            ['--constants', new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\TraitGolf'), null, []],

            // If we didn't ask for 'em, don't include 'em
            ['', new \ReflectionClass($bravo), $bravo, []],
            ['', new \ReflectionClass($charlie), $charlie, []],

            // Include constants even when we ask for more things
            ['--constants --methods --classes', new \ReflectionClass($charlie), $charlie, [
                'Class Constants' => [
                    'B' => [
                        'name'  => 'B',
                        'style' => 'const',
                        'value' => '"\<string>bee\</string>"',
                    ],
                    'C' => [
                        'name'  => 'C',
                        'style' => 'const',
                        'value' => '"\<string>cee\</string>"',
                    ],
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => '"\<string>dee\</string>"',
                    ],
                ],
            ]],
            ['--constants --methods', new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta'), null, [
                'Interface Constants' => [
                    'D' => [
                        'name'  => 'D',
                        'style' => 'const',
                        'value' => '"\<string>dee\</string>"',
                    ],
                ],
            ]],

            // Exclude inherited constants
            ['--constants --no-inherit', new \ReflectionClass($charlie), $charlie, [
                'Class Constants' => [
                    'C' => [
                        'name'  => 'C',
                        'style' => 'const',
                        'value' => '"\<string>cee\</string>"',
                    ],
                ],
            ]],

            // TODO: move `testEnumerateExcludeInherited` into here after PHP 7.1
        ];
    }

    public function testEnumerateExcludeInherited()
    {
        // PHP < 7.1 does the wrong thing with interface constant inheritance, so let's skip for now
        if (\version_compare(PHP_VERSION, '7.1', '<')) {
            return $this->markTestSkipped();
        }

        $enumerator = new ClassConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants --no-inherit');
        $reflector = new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\InterfaceEcho');

        $this->assertEquals([
            'Interface Constants' => [
                'E' => [
                    'name'  => 'E',
                    'style' => 'const',
                    'value' => '"\<string>eee\</string>"',
                ],
            ],
        ], $enumerator->enumerate($input, $reflector));
    }
}
