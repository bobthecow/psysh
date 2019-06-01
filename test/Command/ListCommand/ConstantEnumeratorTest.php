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

use Psy\Command\ListCommand\ConstantEnumerator;

\define('Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT', 42);

class ConstantEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingWithoutFlag()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('');
        $this->assertEquals([], $enumerator->enumerate($input, null, null));
    }

    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants');
        $target = new Fixtures\ClassAlfa();

        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\InterfaceDelta'), $target));
        $this->assertEquals([], $enumerator->enumerate($input, new \ReflectionClass('Psy\Test\Command\ListCommand\Fixtures\TraitFoxtrot'), $target));
    }

    public function testEnumerateInternalConstants()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants --internal');
        $res = $enumerator->enumerate($input);

        $this->assertArrayHasKey('Internal Constants', $res);
        $constants = $res['Internal Constants'];

        $expected = [
            'DATE_ISO8601'           => '"\<string>Y-m-d\TH:i:sO\</string>"',
            'E_USER_WARNING'         => '\<number>512\</number>',
            'FALSE'                  => '\<const>false\</const>',
            'JSON_UNESCAPED_SLASHES' => '\<number>64\</number>',
            'PHP_VERSION'            => '"\<string>' . PHP_VERSION . '\</string>"',
        ];

        foreach ($expected as $name => $value) {
            $this->assertArrayHasKey($name, $constants);
            $this->assertEquals(['name' => $name, 'style' => 'const', 'value' => $value], $constants[$name]);
        }
    }

    public function testEnumerateUserConstants()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants --user');
        $res = $enumerator->enumerate($input);

        $this->assertArrayHasKey('User Constants', $res);
        $constants = $res['User Constants'];

        $unexpected = ['DATE_ISO8601', 'E_USER_WARNING', 'FALSE', 'JSON_UNESCAPED_SLASHES', 'PHP_VERSION'];
        foreach ($unexpected as $internalConst) {
            $this->assertArrayNotHasKey($internalConst, $constants);
        }

        $name = 'Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT';
        $this->assertArrayHasKey($name, $constants);
        $this->assertEquals(['name' => $name, 'style' => 'const', 'value' => '\<number>42\</number>'], $constants[$name]);
    }

    /**
     * @dataProvider categoryConstants
     */
    public function testEnumerateCategoryConstants($category, $label, $expected, $unexpected)
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants --category ' . $category);
        $res = $enumerator->enumerate($input);
        $this->assertArrayHasKey($label, $res);
        $constants = $res[$label];

        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $constants);
        }

        foreach ($unexpected as $name) {
            $this->assertArrayNotHasKey($name, $constants);
        }
    }

    public function categoryConstants()
    {
        return [
            ['core', 'Core Constants', ['E_USER_ERROR', 'PHP_VERSION', 'PHP_EOL', 'TRUE'], ['Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT']],
            ['internal', 'Internal Constants', ['JSON_UNESCAPED_SLASHES', 'E_USER_ERROR', 'PHP_VERSION', 'PHP_EOL', 'TRUE'], ['Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT']],
            ['date', 'Date Constants', ['DATE_ISO8601', 'DATE_COOKIE'], ['E_USER_ERROR', 'JSON_UNESCAPED_SLASHES', 'FALSE']],
            ['json', 'JSON Constants', ['JSON_UNESCAPED_SLASHES'], ['E_USER_ERROR', 'PHP_VERSION', 'PHP_EOL', 'TRUE']],
            ['user', 'User Constants', ['Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT'], ['JSON_UNESCAPED_SLASHES', 'E_USER_ERROR', 'PHP_VERSION', 'PHP_EOL', 'TRUE']],
        ];
    }
}
