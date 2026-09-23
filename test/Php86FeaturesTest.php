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

use PhpParser\Node\Expr\FuncCall;
use Psy\CodeCleaner;
use Psy\Completion\SymbolCatalog;
use Psy\Configuration;
use Psy\Exception\ErrorException;
use Psy\Shell;
use Psy\VarDumper\Presenter;
use Symfony\Component\Console\Formatter\OutputFormatter;

class Php86FeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        if (\PHP_VERSION_ID < 80600 || !\method_exists(FuncCall::class, 'isPartialFunctionApplication')) {
            $this->markTestSkipped('PHP 8.6 and PHP-Parser 5.9 are required.');
        }
    }

    public function testReadonlyPropertyDefaultsSurviveCleaning(): void
    {
        $object = $this->evaluate(<<<'PHP'
new class {
    public readonly int $value = 42;
};
PHP
        );

        $this->assertSame(42, $object->value);

        $property = new \ReflectionProperty($object, 'value');
        $this->assertTrue($property->isReadOnly());
        $this->assertSame(42, $property->getDefaultValue());
    }

    public function testEnumDebugInfoIsPresented(): void
    {
        $enum = $this->evaluate(<<<'PHP'
enum Status: string {
    case Ready = 'ready';

    public function __debugInfo(): array
    {
        return ['description' => 'Ready for work'];
    }
}
Status::Ready;
PHP
        );

        $presenter = new Presenter(new OutputFormatter(false));
        $output = $presenter->present($enum, null, Presenter::RAW);

        $this->assertStringContainsString('description', $output);
        $this->assertStringContainsString('Ready for work', $output);
    }

    public function testOverrideOnClassConstantsSurvivesCleaning(): void
    {
        $constant = $this->evaluate(<<<'PHP'
class ParentClass {
    public const VALUE = 'parent';
}
class ChildClass extends ParentClass {
    #[\Override]
    public const VALUE = 'child';
}
new \ReflectionClassConstant(ChildClass::class, 'VALUE');
PHP
        );

        $this->assertSame('child', $constant->getValue());
        $attributes = $constant->getAttributes(\Override::class);
        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(\Override::class, $attributes[0]->newInstance());
    }

    public function testOverrideOnEnumCasesSurvivesCleaning(): void
    {
        $case = $this->evaluate(<<<'PHP'
interface StatusInterface {
    public const Ready = 'ready';
}
enum Status implements StatusInterface {
    #[\Override]
    case Ready;
}
new \ReflectionEnumUnitCase(Status::class, 'Ready');
PHP
        );

        $this->assertSame('Ready', $case->getValue()->name);
        $attributes = $case->getAttributes(\Override::class);
        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(\Override::class, $attributes[0]->newInstance());
    }

    public function testWeakMapReferenceAssignmentCreatesMissingKey(): void
    {
        $result = $this->evaluate(<<<'PHP'
$map = new \WeakMap();
$key = new \stdClass();
$value = 'before';
$map[$key] =& $value;
$value = 'after';
$map[$key];
PHP
        );

        $this->assertSame('after', $result);
    }

    public function testCatalogDiscoversNewFunctions(): void
    {
        $catalog = new SymbolCatalog();

        $this->assertContains('clamp', $catalog->getFunctions());
    }

    /**
     * @dataProvider runtimeClassProvider
     */
    public function testCatalogDiscoversNewClassesAndNamespaces(string $class, string $namespace): void
    {
        if (!\class_exists($class)) {
            $this->markTestSkipped($class.' is not available in this PHP build.');
        }

        $catalog = new SymbolCatalog();

        $this->assertContains($class, $catalog->getClasses());
        $this->assertContains($namespace, $catalog->getNamespaces());
    }

    public function runtimeClassProvider(): array
    {
        return [
            ['Time\\Duration', 'Time'],
            ['Io\\Poll\\Context', 'Io\\Poll'],
        ];
    }

    /**
     * @dataProvider errorArgumentSettings
     */
    public function testNativeWarningFormatting(string $includeArgs): void
    {
        $previousIncludeArgs = \ini_set('error_include_args', $includeArgs);
        $previousMaxLength = \ini_set('zend.exception_string_param_max_len', '100');
        \set_error_handler([ErrorException::class, 'throwException']);

        try {
            \fopen('<error>-'.\uniqid(), 'r');
            $this->fail('Opening a missing file should emit a warning.');
        } catch (ErrorException $exception) {
            $shell = new Shell(new Configuration(['theme' => 'compact']));
            $formatted = $shell->formatException($exception);
            $this->assertStringContainsString('WARNING', $formatted);
            $this->assertStringContainsString('Failed to open stream', $formatted);
            $this->assertSame($includeArgs === '1', \strpos($formatted, OutputFormatter::escape('<error>')) !== false);
        } finally {
            \restore_error_handler();
            \ini_set('error_include_args', $previousIncludeArgs);
            \ini_set('zend.exception_string_param_max_len', $previousMaxLength);
        }
    }

    public function errorArgumentSettings(): array
    {
        return [['0'], ['1']];
    }

    public function testJsonErrorLocationIsPresented(): void
    {
        try {
            $this->evaluate('json_decode("{", true, 512, JSON_THROW_ON_ERROR);');
            $this->fail('Invalid JSON should throw.');
        } catch (\JsonException $exception) {
            $shell = new Shell(new Configuration(['theme' => 'compact']));
            $formatted = $shell->formatException($exception);
            $this->assertStringContainsString($exception->getMessage(), $formatted);
            $this->assertStringContainsString('location', $formatted);
        }
    }

    private function evaluate(string $code)
    {
        // Isolate declarations so tests may be repeated in the same process.
        $namespace = __NAMESPACE__.'\\Php86Features\\'.\uniqid('Test');
        $cleaner = new CodeCleaner();
        $cleaned = $cleaner->clean(['namespace '.$namespace.'; '.$code]);
        $this->assertNotFalse($cleaned);

        return eval($cleaned);
    }
}
