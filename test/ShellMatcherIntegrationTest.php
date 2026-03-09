<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Configuration;
use Psy\Shell;
use Psy\TabCompletion\Matcher\AbstractMatcher;

/**
 * Integration test for matcher compatibility with CompletionEngine.
 */
class ShellMatcherIntegrationTest extends TestCase
{
    public function testMatchersAddedAtConstruction()
    {
        $matcher = new TestMatcher(['custom1', 'custom2']);

        $config = new Configuration();
        $config->addMatchers([$matcher]);

        $shell = new Shell($config);

        $this->assertCount(1, $this->getMatchers($shell));
    }

    public function testMatchersAddedAtRuntime()
    {
        $config = new Configuration();
        $shell = new Shell($config);

        $this->assertCount(0, $this->getMatchers($shell));

        $matcher = new TestMatcher(['custom1', 'custom2']);
        $shell->addMatchers([$matcher]);

        $this->assertCount(1, $this->getMatchers($shell));
    }

    public function testMultipleMatchersAddedIncrementally()
    {
        $config = new Configuration();
        $shell = new Shell($config);

        $matcher1 = new TestMatcher(['result1']);
        $matcher2 = new TestMatcher(['result2']);
        $matcher3 = new TestMatcher(['result3']);

        $shell->addMatchers([$matcher1]);
        $this->assertCount(1, $this->getMatchers($shell));

        $shell->addMatchers([$matcher2, $matcher3]);
        $this->assertCount(3, $this->getMatchers($shell));
    }

    public function testDeprecatedMethodStillWorks()
    {
        $config = new Configuration();
        $shell = new Shell($config);

        $matcher = new TestMatcher(['result']);

        @$shell->addTabCompletionMatchers([$matcher]);

        $this->assertCount(1, $this->getMatchers($shell));
    }

    /**
     * @return AbstractMatcher[]
     */
    private function getMatchers(Shell $shell): array
    {
        $property = new \ReflectionProperty(Shell::class, 'matchers');
        if (\PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property->getValue($shell);
    }
}

/**
 * Test matcher for integration tests.
 */
class TestMatcher extends AbstractMatcher
{
    private array $results;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMatches(array $tokens, array $info = []): array
    {
        return $this->results;
    }
}
