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

use Psy\Command\ListCommand\ConstantEnumerator;
use Psy\Reflection\ReflectionNamespace;
use Symfony\Component\Console\Formatter\OutputFormatter;

const SOME_CONSTANT = 42;

/**
 * @group isolation-fail
 */
class ConstantEnumeratorTest extends EnumeratorTestCase
{
    const TEST_CONST = 'Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT';

    public function testEnumerateReturnsNothingWithoutFlag()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('');
        $this->assertSame([], $enumerator->enumerate($input, null, null));
    }

    public function testEnumerateReturnsNothingForTarget()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants');
        $target = new Fixtures\ClassAlfa();

        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\InterfaceDelta::class), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\TraitFoxtrot::class), $target));
    }

    public function testEnumerateInternalConstants()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants --internal');
        $res = $enumerator->enumerate($input);

        $this->assertArrayHasKey('Internal Constants', $res);
        $constants = $res['Internal Constants'];

        $expected = [
            'DATE_ISO8601'           => OutputFormatter::escape('"<string>Y-m-d\TH:i:sO</string>"'),
            'E_USER_WARNING'         => $this->presentNumber(512),
            'FALSE'                  => OutputFormatter::escape('<const>false</const>'),
            'JSON_UNESCAPED_SLASHES' => $this->presentNumber(64),
            'PHP_VERSION'            => OutputFormatter::escape('"<string>'.\PHP_VERSION.'</string>"'),
        ];

        foreach ($expected as $name => $value) {
            $this->assertArrayHasKey($name, $constants);
            $this->assertSame(['name' => $name, 'style' => 'const', 'value' => $value], $constants[$name]);
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

        $this->assertArrayHasKey(self::TEST_CONST, $constants);
        $this->assertSame(['name' => self::TEST_CONST, 'style' => 'const', 'value' => $this->presentNumber(42)], $constants[self::TEST_CONST]);
    }

    /**
     * @dataProvider categoryConstants
     */
    public function testEnumerateCategoryConstants($category, $label, $expected, $unexpected)
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants --category '.$category);
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
            ['core', 'Core Constants', ['E_USER_ERROR', 'PHP_VERSION', 'PHP_EOL', 'TRUE'], [self::TEST_CONST]],
            ['internal', 'Internal Constants', ['JSON_UNESCAPED_SLASHES', 'E_USER_ERROR', 'PHP_VERSION', 'PHP_EOL', 'TRUE'], [self::TEST_CONST]],
            ['user', 'User Constants', [self::TEST_CONST], ['JSON_UNESCAPED_SLASHES', 'E_USER_ERROR', 'PHP_VERSION', 'PHP_EOL', 'TRUE']],
            ['date', 'Date Constants', ['DATE_ISO8601', 'DATE_COOKIE'], ['E_USER_ERROR', 'JSON_UNESCAPED_SLASHES', 'FALSE']],
            ['json', 'JSON Constants', ['JSON_UNESCAPED_SLASHES'], ['E_USER_ERROR', 'PHP_VERSION', 'PHP_EOL', 'TRUE']],
        ];
    }

    public function testEnumerateNamespaceConstants()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants');
        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Command\\ListCommand'));
        $this->assertArrayHasKey('Constants', $res);

        $expected = [
            'Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT' => [
                'name'  => 'Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT',
                'style' => 'const',
                'value' => $this->presentNumber(42),
            ],
        ];

        $this->assertSame($expected, $res['Constants']);
    }

    public function testEnumerateInternalAndUserNamespaceConstants()
    {
        $enumerator = new ConstantEnumerator($this->getPresenter());
        $input = $this->getInput('--constants --internal --user');
        $res = $enumerator->enumerate($input, new ReflectionNamespace('Psy\\Test\\Command\\ListCommand'));
        $this->assertArrayHasKey('User Constants', $res);
        $this->assertArrayNotHasKey('Internal Constants', $res);

        $expected = [
            'Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT' => [
                'name'  => 'Psy\\Test\\Command\\ListCommand\\SOME_CONSTANT',
                'style' => 'const',
                'value' => $this->presentNumber(42),
            ],
        ];

        $this->assertSame($expected, $res['User Constants']);
    }
}
