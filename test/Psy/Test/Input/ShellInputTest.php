<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Tests\Input;

use Psy\Input\CodeArgument;
use Psy\Input\ShellInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class ShellInputTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider getTokenizeData
     */
    public function testTokenize($input, $tokens, $message)
    {
        $input = new ShellInput($input);
        $r = new \ReflectionClass('Psy\Input\ShellInput');
        $p = $r->getProperty('tokenPairs');
        $p->setAccessible(true);
        $this->assertEquals($tokens, $p->getValue($input), $message);
    }

    public function testInputOptionWithGivenString()
    {
        $definition = new InputDefinition(array(
            new InputOption('foo', null, InputOption::VALUE_REQUIRED),
            new CodeArgument('code', null, InputOption::VALUE_REQUIRED),
        ));

        $input = new ShellInput('--foo=bar echo "baz\n";');
        $input->bind($definition);
        $this->assertEquals('bar', $input->getOption('foo'));
        $this->assertEquals('echo "baz\n";', $input->getArgument('code'));
    }

    public function testInputOptionWithoutCodeArguments()
    {
        $definition = new InputDefinition(array(
            new InputOption('foo', null, InputOption::VALUE_REQUIRED),
            new InputArgument('bar', null, InputOption::VALUE_REQUIRED),
            new InputArgument('baz', null, InputOption::VALUE_REQUIRED),
        ));

        $input = new ShellInput('--foo=foo bar "baz\n"');
        $input->bind($definition);
        $this->assertEquals('foo', $input->getOption('foo'));
        $this->assertEquals('bar', $input->getArgument('bar'));
        $this->assertEquals("baz\n", $input->getArgument('baz'));
    }

    public function getTokenizeData()
    {
        // Test all the cases from StringInput test, ensuring they have an appropriate $rest token.
        return array(
            array(
                '',
                array(),
                '->tokenize() parses an empty string',
            ),
            array(
                'foo',
                array(array('foo', 'foo')),
                '->tokenize() parses arguments',
            ),
            array(
                '  foo  bar  ',
                array(array('foo', 'foo  bar  '), array('bar', 'bar  ')),
                '->tokenize() ignores whitespaces between arguments',
            ),
            array(
                '"quoted"',
                array(array('quoted', '"quoted"')),
                '->tokenize() parses quoted arguments',
            ),
            array(
                "'quoted'",
                array(array('quoted', "'quoted'")),
                '->tokenize() parses quoted arguments',
            ),
            array(
                "'a\rb\nc\td'",
                array(array("a\rb\nc\td", "'a\rb\nc\td'")),
                '->tokenize() parses whitespace chars in strings',
            ),
            array(
                "'a'\r'b'\n'c'\t'd'",
                array(
                    array('a', "'a'\r'b'\n'c'\t'd'"),
                    array('b', "'b'\n'c'\t'd'"),
                    array('c', "'c'\t'd'"),
                    array('d', "'d'"),
                ),
                '->tokenize() parses whitespace chars between args as spaces',
            ),
            array(
                '\"quoted\"',
                array(array('"quoted"', '\"quoted\"')),
                '->tokenize() parses escaped-quoted arguments',
            ),
            array(
                "\'quoted\'",
                array(array('\'quoted\'', "\'quoted\'")),
                '->tokenize() parses escaped-quoted arguments',
            ),
            array(
                '-a',
                 array(array('-a', '-a')),
                 '->tokenize() parses short options',
             ),
            array(
                '-azc',
                array(array('-azc', '-azc')),
                '->tokenize() parses aggregated short options',
            ),
            array(
                '-awithavalue',
                array(array('-awithavalue', '-awithavalue')),
                '->tokenize() parses short options with a value',
            ),
            array(
                '-a"foo bar"',
                array(array('-afoo bar', '-a"foo bar"')),
                '->tokenize() parses short options with a value',
            ),
            array(
                '-a"foo bar""foo bar"',
                array(array('-afoo barfoo bar', '-a"foo bar""foo bar"')),
                '->tokenize() parses short options with a value',
            ),
            array(
                '-a\'foo bar\'',
                array(array('-afoo bar', '-a\'foo bar\'')),
                '->tokenize() parses short options with a value',
            ),
            array(
                '-a\'foo bar\'\'foo bar\'',
                array(array('-afoo barfoo bar', '-a\'foo bar\'\'foo bar\'')),
                '->tokenize() parses short options with a value',
            ),
            array(
                '-a\'foo bar\'"foo bar"',
                array(array('-afoo barfoo bar', '-a\'foo bar\'"foo bar"')),
                '->tokenize() parses short options with a value',
            ),
            array(
                '--long-option',
                array(array('--long-option', '--long-option')),
                '->tokenize() parses long options',
            ),
            array(
                '--long-option=foo',
                array(array('--long-option=foo', '--long-option=foo')),
                '->tokenize() parses long options with a value',
            ),
            array(
                '--long-option="foo bar"',
                array(array('--long-option=foo bar', '--long-option="foo bar"')),
                '->tokenize() parses long options with a value',
            ),
            array(
                '--long-option="foo bar""another"',
                array(array('--long-option=foo baranother', '--long-option="foo bar""another"')),
                '->tokenize() parses long options with a value',
            ),
            array(
                '--long-option=\'foo bar\'',
                array(array('--long-option=foo bar', '--long-option=\'foo bar\'')),
                '->tokenize() parses long options with a value',
            ),
            array(
                "--long-option='foo bar''another'",
                array(array('--long-option=foo baranother', "--long-option='foo bar''another'")),
                '->tokenize() parses long options with a value',
            ),
            array(
                "--long-option='foo bar'\"another\"",
                array(array('--long-option=foo baranother', "--long-option='foo bar'\"another\"")),
                '->tokenize() parses long options with a value',
            ),
            array(
                'foo -a -ffoo --long bar',
                array(
                    array('foo', 'foo -a -ffoo --long bar'),
                    array('-a', '-a -ffoo --long bar'),
                    array('-ffoo', '-ffoo --long bar'),
                    array('--long', '--long bar'),
                    array('bar', 'bar'),
                ),
                '->tokenize() parses when several arguments and options',
            ),
        );
    }
}
