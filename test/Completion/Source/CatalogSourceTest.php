<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion\Source;

use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;
use Psy\Completion\Source\CatalogSource;
use Psy\Completion\SymbolCatalog;
use Psy\Context;
use Psy\Test\TestCase;

class CatalogSourceTest extends TestCase
{
    private SymbolCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new SymbolCatalog();
    }

    private function makeSource(int $kind, string $method): CatalogSource
    {
        return new CatalogSource($kind, [$this->catalog, $method], $this->catalog);
    }

    /**
     * @dataProvider sourceKindProvider
     */
    public function testAppliesToOwnKind(int $kind, string $method)
    {
        $source = $this->makeSource($kind, $method);

        $this->assertTrue($source->appliesToKind($kind));
    }

    /**
     * @dataProvider sourceKindProvider
     */
    public function testDoesNotApplyToUnrelatedKinds(int $kind, string $method)
    {
        $source = $this->makeSource($kind, $method);

        $this->assertFalse($source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($source->appliesToKind(CompletionKind::STATIC_MEMBER));
        $this->assertFalse($source->appliesToKind(CompletionKind::UNKNOWN));
    }

    /**
     * @dataProvider sourceKindProvider
     */
    public function testEmptyPrefixReturnsSortedResults(int $kind, string $method)
    {
        // Attributes and traits may not exist in all environments
        if ($kind === CompletionKind::ATTRIBUTE_NAME && \PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Attributes require PHP 8.0+');
        }

        $source = $this->makeSource($kind, $method);
        $analysis = new AnalysisResult($kind, '');
        $completions = $source->getCompletions($analysis);

        if ($kind === CompletionKind::TRAIT_NAME && \count($completions) === 0) {
            $this->markTestSkipped('No traits declared in this environment');
        }

        $this->assertGreaterThan(0, \count($completions));

        $sorted = $completions;
        \sort($sorted);
        $this->assertEquals($sorted, $completions);
    }

    public function sourceKindProvider(): array
    {
        return [
            'classes'    => [CompletionKind::CLASS_NAME, 'getClasses'],
            'interfaces' => [CompletionKind::INTERFACE_NAME, 'getInterfaces'],
            'traits'     => [CompletionKind::TRAIT_NAME, 'getTraits'],
            'attributes' => [CompletionKind::ATTRIBUTE_NAME, 'getAttributeClasses'],
            'functions'  => [CompletionKind::FUNCTION_NAME, 'getFunctions'],
            'constants'  => [CompletionKind::CONSTANT, 'getConstants'],
        ];
    }

    /**
     * @dataProvider prefixFilteringProvider
     */
    public function testPrefixFiltering(int $kind, string $method, string $prefix, array $expected)
    {
        $source = $this->makeSource($kind, $method);
        $analysis = new AnalysisResult($kind, $prefix);
        $completions = $source->getCompletions($analysis);

        foreach ($expected as $name) {
            $this->assertContains($name, $completions);
        }
    }

    /**
     * @dataProvider prefixFilteringProvider
     */
    public function testCaseInsensitivePrefixFiltering(int $kind, string $method, string $prefix, array $expected)
    {
        $source = $this->makeSource($kind, $method);

        // Test with inverted case
        $inverted = \ctype_upper($prefix[0]) ? \strtolower($prefix) : \strtoupper($prefix);
        $analysis = new AnalysisResult($kind, $inverted);
        $completions = $source->getCompletions($analysis);

        foreach ($expected as $name) {
            $this->assertContains($name, $completions);
        }
    }

    public function prefixFilteringProvider(): array
    {
        return [
            'classes: Date'    => [CompletionKind::CLASS_NAME, 'getClasses', 'Date', ['DateTime', 'DateTimeZone', 'DateTimeImmutable']],
            'classes: std'     => [CompletionKind::CLASS_NAME, 'getClasses', 'std', ['stdClass']],
            'interfaces: Iter' => [CompletionKind::INTERFACE_NAME, 'getInterfaces', 'Iterator', ['Iterator', 'IteratorAggregate']],
            'functions: str'   => [CompletionKind::FUNCTION_NAME, 'getFunctions', 'str', ['strlen', 'strpos', 'str_replace']],
            'functions: is_'   => [CompletionKind::FUNCTION_NAME, 'getFunctions', 'is_', ['is_array', 'is_string', 'is_int']],
            'constants: PHP_'  => [CompletionKind::CONSTANT, 'getConstants', 'PHP_', ['PHP_VERSION', 'PHP_OS', 'PHP_EOL']],
            'constants: E_'    => [CompletionKind::CONSTANT, 'getConstants', 'E_', ['E_ERROR', 'E_WARNING', 'E_ALL']],
        ];
    }

    /**
     * @dataProvider bitmaskUnionProvider
     */
    public function testAppliesToBitmaskUnions(int $kind, string $method, int $union)
    {
        $source = $this->makeSource($kind, $method);

        $this->assertTrue($source->appliesToKind($union));
    }

    public function bitmaskUnionProvider(): array
    {
        return [
            'CLASS_NAME in CLASS_LIKE'     => [CompletionKind::CLASS_NAME, 'getClasses', CompletionKind::CLASS_LIKE],
            'CLASS_NAME in TYPE_NAME'      => [CompletionKind::CLASS_NAME, 'getClasses', CompletionKind::TYPE_NAME],
            'INTERFACE_NAME in CLASS_LIKE' => [CompletionKind::INTERFACE_NAME, 'getInterfaces', CompletionKind::CLASS_LIKE],
            'INTERFACE_NAME in TYPE_NAME'  => [CompletionKind::INTERFACE_NAME, 'getInterfaces', CompletionKind::TYPE_NAME],
            'TRAIT_NAME in CLASS_LIKE'     => [CompletionKind::TRAIT_NAME, 'getTraits', CompletionKind::CLASS_LIKE],
        ];
    }

    // Source-specific tests

    public function testClassSourceExcludesInterfaces()
    {
        $source = $this->makeSource(CompletionKind::CLASS_NAME, 'getClasses');
        $analysis = new AnalysisResult(CompletionKind::CLASS_NAME, 'Traversable');

        $this->assertNotContains('Traversable', $source->getCompletions($analysis));
    }

    public function testInterfaceSourceExcludesClasses()
    {
        $source = $this->makeSource(CompletionKind::INTERFACE_NAME, 'getInterfaces');
        $analysis = new AnalysisResult(CompletionKind::INTERFACE_NAME, 'DateTime');

        $this->assertNotContains('DateTime', $source->getCompletions($analysis));
    }

    public function testTraitSourceExcludesClassesAndInterfaces()
    {
        $source = $this->makeSource(CompletionKind::TRAIT_NAME, 'getTraits');

        $classAnalysis = new AnalysisResult(CompletionKind::TRAIT_NAME, 'DateTime');
        $this->assertNotContains('DateTime', $source->getCompletions($classAnalysis));

        $ifaceAnalysis = new AnalysisResult(CompletionKind::TRAIT_NAME, 'Iterator');
        $this->assertNotContains('Iterator', $source->getCompletions($ifaceAnalysis));
    }

    public function testFunctionSourceIncludesUserDefined()
    {
        if (!\function_exists('test_catalog_source_func')) {
            eval('function test_catalog_source_func() {}');
        }

        $source = $this->makeSource(CompletionKind::FUNCTION_NAME, 'getFunctions');
        $analysis = new AnalysisResult(CompletionKind::FUNCTION_NAME, 'test_catalog_source');

        $this->assertContains('test_catalog_source_func', $source->getCompletions($analysis));
    }

    public function testConstantSourceIncludesUserDefined()
    {
        if (!\defined('TEST_CATALOG_SOURCE_CONST')) {
            \define('TEST_CATALOG_SOURCE_CONST', 42);
        }

        $source = $this->makeSource(CompletionKind::CONSTANT, 'getConstants');
        $analysis = new AnalysisResult(CompletionKind::CONSTANT, 'TEST_CATALOG_SOURCE');

        $this->assertContains('TEST_CATALOG_SOURCE_CONST', $source->getCompletions($analysis));
    }

    public function testAttributeSourceIncludesBuiltinAttribute()
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Attributes require PHP 8.0+');
        }

        $source = $this->makeSource(CompletionKind::ATTRIBUTE_NAME, 'getAttributeClasses');
        $analysis = new AnalysisResult(CompletionKind::ATTRIBUTE_NAME, '');

        $this->assertContains('Attribute', $source->getCompletions($analysis));
    }

    public function testAttributeSourceExcludesNonAttributes()
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Attributes require PHP 8.0+');
        }

        $source = $this->makeSource(CompletionKind::ATTRIBUTE_NAME, 'getAttributeClasses');
        $analysis = new AnalysisResult(CompletionKind::ATTRIBUTE_NAME, 'DateTime');

        $this->assertNotContains('DateTime', $source->getCompletions($analysis));
    }

    public function testAttributeSourceIncludesCustomAttribute()
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Attributes require PHP 8.0+');
        }

        if (!\class_exists('TestCatalogCustomAttribute')) {
            eval('#[\Attribute] class TestCatalogCustomAttribute {}');
        }

        $source = $this->makeSource(CompletionKind::ATTRIBUTE_NAME, 'getAttributeClasses');
        $analysis = new AnalysisResult(CompletionKind::ATTRIBUTE_NAME, 'TestCatalog');

        $this->assertContains('TestCatalogCustomAttribute', $source->getCompletions($analysis));
    }

    public function testClassSourceReturnsFullyQualifiedNamesForNamespacedPrefix()
    {
        \class_exists(Context::class);

        $source = $this->makeSource(CompletionKind::CLASS_NAME, 'getClasses');
        $analysis = new AnalysisResult(CompletionKind::CLASS_NAME, 'Psy\\C');

        $completions = $source->getCompletions($analysis);

        $this->assertContains('Psy\\Context', $completions);
    }
}
