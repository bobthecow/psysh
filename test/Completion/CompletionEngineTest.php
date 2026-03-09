<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion;

use Psy\Completion\CompletionEngine;
use Psy\Completion\CompletionRequest;
use Psy\Completion\Source\VariableSource;
use Psy\Completion\SymbolCatalog;
use Psy\Context;
use Psy\Test\Fixtures\Completion\FixedResultSource;
use Psy\Test\TestCase;

class CompletionEngineTest extends TestCase
{
    public function testMultipleSourceInstancesAreNotClobbered(): void
    {
        $engine = new CompletionEngine(new Context());
        $engine->addSource(new FixedResultSource(['first']));
        $engine->addSource(new FixedResultSource(['second']));

        $result = $engine->getCompletions(new CompletionRequest('$', 1));

        $this->assertContains('first', $result);
        $this->assertContains('second', $result);
    }

    public function testSameSourceInstanceIsNotRegisteredTwice(): void
    {
        $engine = new CompletionEngine(new Context());
        $source = new FixedResultSource(['only_once']);
        $engine->addSource($source);
        $engine->addSource($source);

        $result = $engine->getCompletions(new CompletionRequest('$', 1));

        $this->assertSame(['only_once'], $result);
    }

    public function testAllCatalogSourceKindsAreRegistered(): void
    {
        $catalog = new SymbolCatalog();
        $engine = new CompletionEngine(new Context(), null, $catalog);
        $engine->registerDefaultSources();

        // Classes are always available in the catalog.
        $result = $engine->getCompletions(new CompletionRequest('stdCl', 5));
        $this->assertContains('stdClass', $result);

        // Functions too.
        $result = $engine->getCompletions(new CompletionRequest('array_ma', 8));
        $this->assertContains('array_map', $result);
    }

    public function testCompletionsReflectContextChanges(): void
    {
        $context = new Context();
        $context->setAll(['foo' => 123]);

        $engine = new CompletionEngine($context);
        $engine->addSource(new VariableSource($context));

        $first = $engine->getCompletions(new CompletionRequest('$f', 2));
        $this->assertContains('foo', $first);

        $context->setAll(['bar' => 456]);

        $second = $engine->getCompletions(new CompletionRequest('$f', 2));
        $this->assertSame([], $second);
    }

    public function testStaticFactoryResultSupportsMemberCompletion(): void
    {
        $engine = new CompletionEngine(new Context());
        $engine->registerDefaultSources();

        $input = '\\Psy\\Test\\Fixtures\\Completion\\CompletionEngineFactoryFixture::create()->for';
        $result = $engine->getCompletions(new CompletionRequest($input, \strlen($input)));

        $this->assertContains('format', $result);
    }

    public function testRequestCursorIsNormalizedToBufferLength(): void
    {
        $context = new Context();
        $context->setAll(['foo' => 123]);

        $engine = new CompletionEngine($context);
        $engine->addSource(new VariableSource($context));

        $result = $engine->getCompletions(new CompletionRequest('$f', 999));

        $this->assertContains('foo', $result);
    }
}
