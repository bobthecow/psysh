<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\TabCompletion;

use Psy\Command\ListCommand;
use Psy\Command\ShowCommand;
use Psy\Completion\CompletionEngine;
use Psy\Completion\Source\CommandSource;
use Psy\Completion\Source\MatcherAdapterSource;
use Psy\Completion\Source\VariableSource;
use Psy\Configuration;
use Psy\Context;
use Psy\TabCompletion\Matcher;
use Psy\Test\Fixtures\Completion\InfoCapturingMatcher;

class AutoCompleterTest extends \Psy\Test\TestCase
{
    public function testCompletionEngineModeUsesCanonicalSources()
    {
        $context = new Context();
        $context->setAll(['foo' => 12, 'bar' => new \DOMDocument()]);

        $engine = new CompletionEngine($context);
        $engine->addSource(new VariableSource($context));
        $engine->addSource(new CommandSource([
            new ShowCommand(),
            new ListCommand(),
        ]));

        $tabCompletion = new \Psy\TabCompletion\AutoCompleter();
        $tabCompletion->setCompletionEngine($engine);

        $variables = $tabCompletion->processCallback('', 0, [
            'line_buffer' => '$f',
            'point'       => 0,
            'end'         => 2,
        ]);
        $commands = $tabCompletion->processCallback('', 0, [
            'line_buffer' => 'sh',
            'point'       => 0,
            'end'         => 2,
        ]);

        $this->assertContains('foo', $variables);
        $this->assertContains('show', $commands);
    }

    public function testCompletionEngineModeKeepsMatcherCompatibility()
    {
        require_once __DIR__.'/../Fixtures/TabCompletion/default_parameter_completion_fixture.php';

        $engine = new CompletionEngine(new Context());
        $engine->addSource(new MatcherAdapterSource([
            new Matcher\FunctionDefaultParametersMatcher(),
        ]));

        $tabCompletion = new \Psy\TabCompletion\AutoCompleter();
        $tabCompletion->setCompletionEngine($engine);

        $line = 'psysh_tab_completion_default_param_fixture(';
        $matches = $tabCompletion->processCallback('', 0, [
            'line_buffer' => $line,
            'point'       => 0,
            'end'         => \strlen($line),
        ]);

        $this->assertContains('$flags = 42, $options = ["baz"])', $matches);
    }

    public function testThrowsWithoutCompletionEngine()
    {
        $tabCompletion = new \Psy\TabCompletion\AutoCompleter();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('AutoCompleter requires a CompletionEngine.');

        $tabCompletion->processCallback('', 0, ['line_buffer' => '$f', 'end' => 2]);
    }

    public function testCustomMatcherAddedBeforeCompletionEngineIsUsed()
    {
        $tabCompletion = new \Psy\TabCompletion\AutoCompleter();
        $tabCompletion->addMatcher(new TestMatcher(['custom-before']));
        $tabCompletion->setCompletionEngine(new CompletionEngine(new Context()));

        $matches = $tabCompletion->processCallback('', 0, [
            'line_buffer' => '',
            'point'       => 0,
            'end'         => 0,
        ]);

        $this->assertContains('custom-before', $matches);
    }

    public function testCustomMatcherAddedAfterCompletionEngineIsUsed()
    {
        $tabCompletion = new \Psy\TabCompletion\AutoCompleter();
        $tabCompletion->setCompletionEngine(new CompletionEngine(new Context()));
        $tabCompletion->addMatcher(new TestMatcher(['custom-after']));

        $matches = $tabCompletion->processCallback('', 0, [
            'line_buffer' => '',
            'point'       => 0,
            'end'         => 0,
        ]);

        $this->assertContains('custom-after', $matches);
    }

    public function testCustomMatcherReceivesOriginalReadlineInfo()
    {
        $matcher = new InfoCapturingMatcher();

        $tabCompletion = new \Psy\TabCompletion\AutoCompleter();
        $tabCompletion->addMatcher($matcher);
        $tabCompletion->setCompletionEngine(new CompletionEngine(new Context()));

        $tabCompletion->processCallback('', 0, [
            'line_buffer' => '$foo trailing text',
            'point'       => 2,
            'end'         => 4,
            'mark'        => 1,
        ]);

        $this->assertSame([
            'line_buffer' => '$foo trailing text',
            'point'       => 2,
            'end'         => 4,
            'mark'        => 1,
        ], $matcher->getLastInfo());
    }

    /**
     * Tracks known CompletionEngine gaps against the legacy matcher path for
     * standard readline. Remove the skip once the refactor reaches parity.
     *
     * @param string $line
     * @param array  $mustContain
     * @param array  $mustNotContain
     *
     * @dataProvider legacyParityInput
     */
    public function testKnownLegacyParityGaps($line, $mustContain, $mustNotContain)
    {
        $this->markTestSkipped('CompletionEngine parity gaps are tracked here while refactor is in progress.');

        $code = $this->completeWithEngine($line);

        foreach ($mustContain as $mc) {
            $this->assertContains($mc, $code);
        }

        foreach ($mustNotContain as $mnc) {
            $this->assertNotContains($mnc, $code);
        }
    }

    /**
     * @param string $line
     * @param array  $mustContain
     * @param array  $mustNotContain
     *
     * @dataProvider classesInput
     */
    public function testClassesCompletion($line, $mustContain, $mustNotContain)
    {
        $code = $this->completeWithEngine($line);

        foreach ($mustContain as $mc) {
            $this->assertContains($mc, $code);
        }

        foreach ($mustNotContain as $mnc) {
            $this->assertNotContains($mnc, $code);
        }
    }

    /**
     * @return array
     */
    public function legacyParityInput()
    {
        return [
            'interface symbol completion' => [
                'DateT',
                ['DateTimeInterface'],
                [],
            ],
            'unqualified constructor target' => [
                'new ',
                ['stdClass', Context::class, Configuration::class],
                ['require', 'array_search', 'T_OPEN_TAG', '$foo'],
            ],
            'qualified constructor target' => [
                'new Psy\\C',
                ['Context'],
                ['CASE_LOWER'],
            ],
            'variable after infix expression' => [
                '6 + $b',
                ['bar'],
                [],
            ],
            'namespace member suggestions' => [
                'Psy\\',
                ['Context', 'TabCompletion\\Matcher\\AbstractMatcher'],
                ['require', 'array_search'],
            ],
        ];
    }

    /**
     * @return array
     */
    public function classesInput()
    {
        return [
            // input, must contain, must not contain
            ['T_OPE', ['T_OPEN_TAG'], []],
            ['stdCla', ['stdClass'], []],
            ['DateT', ['DateTime', 'DateTimeImmutable', 'DateTimeZone'], []],
            ['new s', ['stdClass'], []],
            ['array_', ['array_search', 'array_map', 'array_merge'], []],
            ['$bar->', ['load'], []],
            ['$b', ['bar'], []],
            ['$f', ['foo'], []],
            ['ls ', [], ['ls']],
            ['sho', ['show'], []],
            ['12 + clone $', ['foo'], []],
            ['$', ['foo', 'bar'], ['require', 'array_search', 'T_OPEN_TAG']],
            [
                'Psy\Test\Fixtures\TabCompletion\StaticSample::CO',
                ['CONSTANT_VALUE'],
                [],
            ],
            [
                'Psy\Test\Fixtures\TabCompletion\StaticSample::',
                ['staticVariable'],
                [],
            ],
            [
                'Psy\Test\Fixtures\TabCompletion\StaticSample::',
                ['staticFunction'],
                [],
            ],
        ];
    }

    private function completeWithEngine(string $line): array
    {
        $context = new Context();

        $commands = [
            new ShowCommand(),
            new ListCommand(),
        ];

        $engine = new CompletionEngine($context);
        $engine->registerDefaultSources([
            new CommandSource($commands),
        ]);

        $tabCompletion = new \Psy\TabCompletion\AutoCompleter();
        $tabCompletion->setCompletionEngine($engine);

        $context->setAll(['foo' => 12, 'bar' => new \DOMDocument()]);

        return $tabCompletion->processCallback('', 0, [
            'line_buffer' => $line,
            'point'       => 0,
            'end'         => \strlen($line),
        ]);
    }
}

class TestMatcher extends Matcher\AbstractMatcher
{
    /** @var string[] */
    private array $results;

    /**
     * @param string[] $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function hasMatched(array $tokens): bool
    {
        return true;
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        return $this->results;
    }
}
