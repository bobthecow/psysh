<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Completion;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard as Printer;
use Psy\CodeCleaner;
use Psy\ParserFactory;

/**
 * Analyzes input to determine completion context.
 *
 * Uses php-parser and CodeCleaner to build an AST with proper namespace
 * and use statement context for type-aware completions.
 */
class ContextAnalyzer
{
    private Parser $parser;
    private Printer $printer;
    private ?CodeCleaner $cleaner;

    public function __construct(?CodeCleaner $cleaner = null)
    {
        $this->parser = (new ParserFactory())->createParser();
        $this->printer = new Printer();
        $this->cleaner = $cleaner;
    }

    /**
     * Analyze input and return completion context.
     */
    public function analyze(string $input, int $cursor): AnalysisResult
    {
        // Cursor is in code-point units, so use mb_substr
        $inputToCursor = \mb_substr($input, 0, $cursor);
        $cursorAtEnd = ($cursor >= \mb_strlen($input));
        $commandAnalysis = $this->analyzeCommandInput($inputToCursor, $cursorAtEnd);

        $analysis = $this->tryParse($inputToCursor, $cursorAtEnd)
            ?? $this->analyzePartialInput($inputToCursor, $cursorAtEnd);

        if ($commandAnalysis !== null) {
            if ($commandAnalysis->kinds === CompletionKind::COMMAND_OPTION) {
                $analysis = $commandAnalysis;
            } elseif (($analysis->kinds & CompletionKind::SYMBOL) !== 0 || $analysis->kinds === CompletionKind::UNKNOWN) {
                $analysis = $commandAnalysis;
            }
        }

        $analysis->input = $inputToCursor;

        return $analysis;
    }

    /**
     * Try to parse input and analyze the resulting AST.
     */
    private function tryParse(string $input, bool $cursorAtEnd): ?AnalysisResult
    {
        $code = '<?php '.$input;

        // Try with semicolon first (most common case), then without
        try {
            $stmts = $this->parser->parse($code.';');
        } catch (\PhpParser\Error $e) {
            try {
                $stmts = $this->parser->parse($code);
            } catch (\PhpParser\Error $e2) {
                return null;
            }
        }

        if (empty($stmts)) {
            return new AnalysisResult(CompletionKind::UNKNOWN, '');
        }

        $visitor = new DeepestNodeVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        $node = $visitor->getDeepestNode();
        if ($node === null) {
            return new AnalysisResult(CompletionKind::UNKNOWN, '');
        }

        // Trailing whitespace means the user has moved past this token
        if ($cursorAtEnd && $input !== \rtrim($input)) {
            return new AnalysisResult(CompletionKind::UNKNOWN, '');
        }

        return $this->analyzeNode($node);
    }

    /**
     * Analyze a specific AST node to determine completion context.
     */
    private function analyzeNode(Node $node): AnalysisResult
    {
        // $foo->bar, $foo->bar(), $foo?->bar, $foo?->bar()
        if (
            $node instanceof MethodCall
            || $node instanceof PropertyFetch
            || $node instanceof NullsafeMethodCall
            || $node instanceof NullsafePropertyFetch
        ) {
            $leftSide = $this->extractExpression($node->var);
            $prefix = $node->name instanceof Identifier ? $node->name->name : '';
            $result = new AnalysisResult(CompletionKind::OBJECT_MEMBER, $prefix, $leftSide);
            $result->leftSideNode = $node->var;

            return $result;
        }

        // Foo::bar(), Foo::BAR, Foo::$bar
        if (
            $node instanceof StaticCall
            || $node instanceof ClassConstFetch
            || $node instanceof StaticPropertyFetch
        ) {
            $leftSide = $this->extractExpression($node->class);
            $prefix = $node->name instanceof Identifier ? $node->name->name : '';
            $result = new AnalysisResult(CompletionKind::STATIC_MEMBER, $prefix, $leftSide);
            $result->leftSideNode = $node->class;

            return $result;
        }

        // new Foo
        if ($node instanceof New_) {
            $prefix = $node->class instanceof Name ? $node->class->toString() : '';

            return new AnalysisResult(CompletionKind::CLASS_NAME, $prefix);
        }

        // $foo
        if ($node instanceof Variable) {
            $prefix = \is_string($node->name) ? $node->name : '';

            return new AnalysisResult(CompletionKind::VARIABLE, $prefix);
        }

        // foo()
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            return new AnalysisResult(CompletionKind::FUNCTION_NAME, $node->name->toString());
        }

        // FOO (could be a constant, function, or class reference)
        if ($node instanceof ConstFetch && $node->name instanceof Name) {
            return new AnalysisResult(CompletionKind::SYMBOL, $node->name->toString());
        }

        if ($node instanceof Identifier) {
            return new AnalysisResult(CompletionKind::UNKNOWN, $node->name);
        }

        // Bare name: foo or Foo\Bar
        if ($node instanceof Name) {
            return new AnalysisResult(CompletionKind::SYMBOL, $node->toString());
        }

        return new AnalysisResult(CompletionKind::UNKNOWN, '');
    }

    /**
     * Analyze partial/incomplete input that doesn't parse.
     */
    private function analyzePartialInput(string $input, bool $cursorAtEnd): AnalysisResult
    {
        $trimmed = \rtrim($input);
        $hasTrailingSpace = $cursorAtEnd && $input !== $trimmed;

        // Statement start after semicolon/brace: keywords but NOT commands
        if (\preg_match('/^.*[;\{\}]\s*(\w+)$/', $trimmed, $matches)) {
            if (!\preg_match('/(?:->|\?->)\w*$/', $trimmed) && !\preg_match('/::\w*$/', $trimmed)) {
                if ($hasTrailingSpace) {
                    return new AnalysisResult(CompletionKind::UNKNOWN, '');
                }

                return new AnalysisResult(
                    CompletionKind::KEYWORD | CompletionKind::SYMBOL,
                    $matches[1]
                );
            }
        }

        // $foo-> or $foo->bar or $foo?->bar
        if (\preg_match('/(\$\w+)(?:->|\?->)([\w]*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::OBJECT_MEMBER, $matches[2], $matches[1]);
        }

        // Foo::$bar or Foo::$b
        if (\preg_match('/([\w\\\\]+)::\$(\w*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::STATIC_MEMBER, $matches[2], $matches[1]);
        }

        // Foo:: or Foo::bar
        if (\preg_match('/([\w\\\\]+)::([\w]*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::STATIC_MEMBER, $matches[2], $matches[1]);
        }

        // $ or $f
        if (\preg_match('/\$(\w*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::VARIABLE, $matches[1]);
        }

        // new or new F
        if (\preg_match('/\bnew\s+([\w\\\\]*)$/i', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::CLASS_NAME, $matches[1]);
        }

        // Partial identifier at end
        if (\preg_match('/([\w\\\\]+)$/', $trimmed, $matches)) {
            if ($hasTrailingSpace) {
                return new AnalysisResult(CompletionKind::UNKNOWN, '');
            }

            return new AnalysisResult(CompletionKind::SYMBOL, $matches[1]);
        }

        return new AnalysisResult(CompletionKind::UNKNOWN, '');
    }

    /**
     * Analyze command-specific contexts that can parse as valid PHP.
     */
    private function analyzeCommandInput(string $input, bool $cursorAtEnd): ?AnalysisResult
    {
        $trimmed = \rtrim($input);
        $hasTrailingSpace = $cursorAtEnd && $input !== $trimmed;

        // Command option: "ls --opt", "ls -a", or "ls target --opt".
        // We check this before PHP parsing, because short opts (e.g. "-a")
        // parse as subtraction expressions.
        if (\preg_match('/^([a-z][a-z0-9-]*)\s+.*?(-{1,2}[\w-]*)$/', $trimmed, $matches)) {
            return new AnalysisResult(CompletionKind::COMMAND_OPTION, $matches[2], $matches[1]);
        }

        // Single command-like word at the start of input.
        if (\preg_match('/^([a-z][a-z0-9-]*)$/', $trimmed, $matches)) {
            if ($hasTrailingSpace) {
                return new AnalysisResult(CompletionKind::UNKNOWN, '');
            }

            return new AnalysisResult(
                CompletionKind::COMMAND | CompletionKind::KEYWORD | CompletionKind::SYMBOL,
                $matches[1]
            );
        }

        return null;
    }

    /**
     * Extract a string representation of an expression.
     *
     * Uses the printer to convert the AST node back to code.
     */
    private function extractExpression($expr): string
    {
        if ($expr instanceof Variable && \is_string($expr->name)) {
            return '$'.$expr->name;
        }

        if ($expr instanceof Name) {
            return $this->resolveClassName($expr);
        }

        if ($expr instanceof Node\Expr) {
            return $this->printer->prettyPrintExpr($expr);
        }

        return '';
    }

    /**
     * Resolve a class name using CodeCleaner's namespace context.
     *
     * This ensures we use the same use statements and namespace as the code
     * being executed.
     */
    private function resolveClassName(Name $name): string
    {
        if ($this->cleaner === null) {
            return $name->toString();
        }

        return $this->cleaner->resolveClassName($name->toString());
    }
}
