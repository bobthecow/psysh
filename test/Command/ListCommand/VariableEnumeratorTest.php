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

use Psy\Command\ListCommand\VariableEnumerator;
use Psy\Context;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * @group isolation-fail
 */
class VariableEnumeratorTest extends EnumeratorTestCase
{
    public function testEnumerateReturnsNothingWithoutFlag()
    {
        $context = new Context();
        $context->setAll([
            'one'   => 1,
            'two'   => 'two',
            'three' => [true, false, null],
        ]);

        $enumerator = new VariableEnumerator($this->getPresenter(), $context);
        $input = $this->getInput('');
        $this->assertSame([], $enumerator->enumerate($input));
    }

    public function testEnumerateReturnsNothingForTarget()
    {
        $context = new Context();
        $context->setAll([
            'one'   => 1,
            'two'   => 'two',
            'three' => [true, false, null],
        ]);

        $enumerator = new VariableEnumerator($this->getPresenter(), $context);
        $input = $this->getInput('--vars');
        $target = new Fixtures\ClassAlfa();

        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), null));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass($target), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\InterfaceDelta::class), $target));
        $this->assertSame([], $enumerator->enumerate($input, new \ReflectionClass(Fixtures\TraitFoxtrot::class), $target));
    }

    public function testEnumerateEnumerates()
    {
        $context = new Context();
        $context->setAll([
            'one'   => 1,
            'two'   => 'two',
            'three' => [true, false, null],
        ]);

        $enumerator = new VariableEnumerator($this->getPresenter(), $context);
        $input = $this->getInput('--vars');
        $res = $enumerator->enumerate($input);

        $this->assertArrayHasKey('Variables', $res);
        $vars = $res['Variables'];

        $this->assertEquals([
            '$one' => [
                'name'  => '$one',
                'style' => 'public',
                'value' => $this->presentNumber(1),
            ],
            '$two' => [
                'name'  => '$two',
                'style' => 'public',
                'value' => OutputFormatter::escape('"<string>two</string>"'),
            ],
            '$three' => [
                'name'  => '$three',
                'style' => 'public',
                'value' => '[ …3]',
            ],
        ], $vars);
    }

    public function testEnumerateAllEnumeratesEvenMore()
    {
        $context = new Context();
        $context->setAll([
            'one'   => 1,
            'two'   => 'two',
            'three' => [true, false, null],
        ]);

        $exception = new \Exception('Wheeeee');
        $context->setLastException($exception);

        $context->setLastStdout('last stdout');

        $enumerator = new VariableEnumerator($this->getPresenter(), $context);
        $input = $this->getInput('--vars --all');
        $res = $enumerator->enumerate($input);

        $this->assertArrayHasKey('Variables', $res);
        $vars = $res['Variables'];

        $this->assertEquals([
            '$one' => [
                'name'  => '$one',
                'style' => 'public',
                'value' => $this->presentNumber(1),
            ],
            '$two' => [
                'name'  => '$two',
                'style' => 'public',
                'value' => OutputFormatter::escape('"<string>two</string>"'),
            ],
            '$three' => [
                'name'  => '$three',
                'style' => 'public',
                'value' => '[ …3]',
            ],
            '$_' => [
                'name'  => '$_',
                'style' => 'private',
                'value' => OutputFormatter::escape('<const>null</const>'),
            ],
            '$_e' => [
                'name'  => '$_e',
                'style' => 'private',
                'value' => $this->getPresenter()->presentRef($exception),
            ],
            '$__out' => [
                'name'  => '$__out',
                'style' => 'private',
                'value' => OutputFormatter::escape('"<string>last stdout</string>"'),
            ],
        ], $vars);
    }
}
