<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard as Printer;
use Psy\CodeCleaner\AbstractClassPass;
use Psy\CodeCleaner\AssignThisVariablePass;
use Psy\CodeCleaner\CalledClassPass;
use Psy\CodeCleaner\CallTimePassByReferencePass;
use Psy\CodeCleaner\CodeCleanerPass;
use Psy\CodeCleaner\EmptyArrayDimFetchPass;
use Psy\CodeCleaner\ExitPass;
use Psy\CodeCleaner\FinalClassPass;
use Psy\CodeCleaner\FunctionContextPass;
use Psy\CodeCleaner\FunctionReturnInWriteContextPass;
use Psy\CodeCleaner\ImplicitReturnPass;
use Psy\CodeCleaner\ImplicitUsePass;
use Psy\CodeCleaner\IssetPass;
use Psy\CodeCleaner\LabelContextPass;
use Psy\CodeCleaner\LeavePsyshAlonePass;
use Psy\CodeCleaner\ListPass;
use Psy\CodeCleaner\LoopContextPass;
use Psy\CodeCleaner\MagicConstantsPass;
use Psy\CodeCleaner\NamespacePass;
use Psy\CodeCleaner\PassableByReferencePass;
use Psy\CodeCleaner\RequirePass;
use Psy\CodeCleaner\ReturnTypePass;
use Psy\CodeCleaner\StrictTypesPass;
use Psy\CodeCleaner\UseStatementPass;
use Psy\CodeCleaner\ValidClassNamePass;
use Psy\CodeCleaner\ValidConstructorPass;
use Psy\CodeCleaner\ValidFunctionNamePass;
use Psy\Exception\ParseErrorException;
use Psy\Util\Str;

/**
 * A service to clean up user input, detect parse errors before they happen,
 * and generally work around issues with the PHP code evaluation experience.
 */
class CodeCleaner
{
    private bool $yolo = false;
    private bool $strictTypes = false;
    private $implicitUse = false;

    private Parser $parser;
    private Printer $printer;
    private NodeTraverser $traverser;
    private NodeTraverser $nameResolver;
    private ?array $namespace = null;
    private array $messages = [];

    /**
     * CodeCleaner constructor.
     *
     * @param Parser|null        $parser      A PhpParser Parser instance. One will be created if not explicitly supplied
     * @param Printer|null       $printer     A PhpParser Printer instance. One will be created if not explicitly supplied
     * @param NodeTraverser|null $traverser   A PhpParser NodeTraverser instance. One will be created if not explicitly supplied
     * @param bool               $yolo        run without input validation
     * @param bool               $strictTypes enforce strict types by default
     * @param false|array        $implicitUse disable implicit use statements (false) or configure with namespace filters (array)
     */
    public function __construct(?Parser $parser = null, ?Printer $printer = null, ?NodeTraverser $traverser = null, bool $yolo = false, bool $strictTypes = false, $implicitUse = false)
    {
        $this->yolo = $yolo;
        $this->strictTypes = $strictTypes;
        $this->implicitUse = \is_array($implicitUse) ? $implicitUse : false;

        $this->parser = $parser ?? (new ParserFactory())->createParser();
        $this->printer = $printer ?: new Printer();
        $this->traverser = $traverser ?: new NodeTraverser();
        $this->nameResolver = new NodeTraverser();

        foreach ($this->getDefaultPasses() as $pass) {
            $this->traverser->addVisitor($pass);

            // Add only name resolution passes to the name resolver traverser
            // These share state with the main traverser since they're the same instances
            if ($pass instanceof UseStatementPass ||
                $pass instanceof ImplicitUsePass ||
                $pass instanceof NamespacePass) {
                $this->nameResolver->addVisitor($pass);
            }
        }
    }

    /**
     * Check whether this CodeCleaner is in YOLO mode.
     */
    public function yolo(): bool
    {
        return $this->yolo;
    }

    /**
     * Get default CodeCleaner passes.
     *
     * @return CodeCleanerPass[]
     */
    private function getDefaultPasses(): array
    {
        $useStatementPass = new UseStatementPass();
        $namespacePass = new NamespacePass($this);

        // Try to add implicit `use` statements and an implicit namespace,
        // based on the file in which the `debug` call was made.
        $this->addImplicitDebugContext([$useStatementPass, $namespacePass]);

        // Add implicit use pass if enabled (must run before use statement pass)
        $usePasses = [$useStatementPass];
        if ($this->implicitUse) {
            \array_unshift($usePasses, new ImplicitUsePass($this->implicitUse, $this));
        }

        // A set of code cleaner passes that don't try to do any validation, and
        // only do minimal rewriting to make things work inside the REPL.
        //
        // When in --yolo mode, these are the only code cleaner passes used.
        $rewritePasses = [
            new LeavePsyshAlonePass(),
            ...$usePasses,            // must run before namespace pass
            new ExitPass(),
            new ImplicitReturnPass(),
            new MagicConstantsPass(),
            $namespacePass,           // must run after the implicit return pass
            new RequirePass(),
            new StrictTypesPass($this->strictTypes),
        ];

        if ($this->yolo) {
            return $rewritePasses;
        }

        return [
            // Validation passes
            new AbstractClassPass(),
            new AssignThisVariablePass(),
            new CalledClassPass(),
            new CallTimePassByReferencePass(),
            new FinalClassPass(),
            new FunctionContextPass(),
            new FunctionReturnInWriteContextPass(),
            new IssetPass(),
            new LabelContextPass(),
            new ListPass(),
            new LoopContextPass(),
            new PassableByReferencePass(),
            new ReturnTypePass(),
            new EmptyArrayDimFetchPass(),
            new ValidConstructorPass(),

            // Rewriting shenanigans
            ...$rewritePasses,

            // Namespace-aware validation (which depends on aforementioned shenanigans)
            new ValidClassNamePass(),
            new ValidFunctionNamePass(),
        ];
    }

    /**
     * "Warm up" code cleaner passes when we're coming from a debug call.
     *
     * This is useful, for example, for `UseStatementPass` and `NamespacePass`
     * which keep track of state between calls, to maintain the current
     * namespace and a map of use statements.
     *
     * @param array $passes
     */
    private function addImplicitDebugContext(array $passes)
    {
        $file = $this->getDebugFile();
        if ($file === null) {
            return;
        }

        try {
            $code = @\file_get_contents($file);
            if (!$code) {
                return;
            }

            $stmts = $this->parse($code, true);
            if ($stmts === false) {
                return;
            }

            // Set up a clean traverser for just these code cleaner passes
            // @todo Pass visitors directly to once we drop support for PHP-Parser 4.x
            $traverser = new NodeTraverser();
            foreach ($passes as $pass) {
                $traverser->addVisitor($pass);
            }

            $traverser->traverse($stmts);
        } catch (\Throwable $e) {
            // Don't care.
        }
    }

    /**
     * Search the stack trace for a file in which the user called Psy\debug.
     *
     * @return string|null
     */
    private static function getDebugFile()
    {
        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach (\array_reverse($trace) as $stackFrame) {
            if (!self::isDebugCall($stackFrame)) {
                continue;
            }

            if (\preg_match('/eval\(/', $stackFrame['file'])) {
                \preg_match_all('/([^\(]+)\((\d+)/', $stackFrame['file'], $matches);

                return $matches[1][0];
            }

            return $stackFrame['file'];
        }

        return null;
    }

    /**
     * Check whether a given backtrace frame is a call to Psy\debug.
     *
     * @param array $stackFrame
     */
    private static function isDebugCall(array $stackFrame): bool
    {
        $class = isset($stackFrame['class']) ? $stackFrame['class'] : null;
        $function = isset($stackFrame['function']) ? $stackFrame['function'] : null;

        return ($class === null && $function === 'Psy\\debug') ||
            ($class === Shell::class && $function === 'debug');
    }

    /**
     * Clean the given array of code.
     *
     * @throws ParseErrorException if the code is invalid PHP, and cannot be coerced into valid PHP
     *
     * @param array $codeLines
     * @param bool  $requireSemicolons
     *
     * @return string|false Cleaned PHP code, False if the input is incomplete
     */
    public function clean(array $codeLines, bool $requireSemicolons = false)
    {
        // Clear messages from previous clean
        $this->messages = [];

        $stmts = $this->parse('<?php '.\implode(\PHP_EOL, $codeLines).\PHP_EOL, $requireSemicolons);
        if ($stmts === false) {
            return false;
        }

        // Catch fatal errors before they happen
        $stmts = $this->traverser->traverse($stmts);

        // Work around https://github.com/nikic/PHP-Parser/issues/399
        $oldLocale = \setlocale(\LC_NUMERIC, 0);
        \setlocale(\LC_NUMERIC, 'C');

        $code = $this->printer->prettyPrint($stmts);

        // Now put the locale back
        \setlocale(\LC_NUMERIC, $oldLocale);

        return $code;
    }

    /**
     * Set the current local namespace.
     */
    public function setNamespace(?array $namespace = null)
    {
        $this->namespace = $namespace;
    }

    /**
     * Get the current local namespace.
     *
     * @return array|null
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Resolve a class name using current use statements and namespace.
     *
     * This is used by commands to resolve short names the same way code execution does.
     * Uses a minimal traverser with only name resolution passes (no validation).
     *
     * @param string $name Class name to resolve (e.g., "NoopChecker" or "Bar\Baz")
     *
     * @return string Resolved class name (may be FQN, or original name if no resolution found)
     */
    public function resolveClassName(string $name): string
    {
        // Clear messages from previous resolution
        $this->messages = [];

        // Only attempt resolution if it's a valid class name, and not already fully qualified
        if (\substr($name, 0, 1) === '\\' || !Str::isValidClassName($name)) {
            return $name;
        }

        try {
            // Parse as a class name constant, and transform using name resolution passes
            $stmts = $this->parser->parse('<?php '.$name.'::class;');
            $stmts = $this->nameResolver->traverse($stmts);

            // Extract resolved name from transformed AST
            if (isset($stmts[0]) && $stmts[0] instanceof Expression) {
                $expr = $stmts[0]->expr;
                if ($expr instanceof ClassConstFetch) {
                    $class = $expr->class;
                    if ($class instanceof FullyQualified) {
                        return '\\'.$class->toString();
                    } elseif ($class instanceof Name) {
                        // Not fully qualified, might be in current namespace
                        $resolved = $class->toString();
                        if ($this->namespace) {
                            $namespacedName = \implode('\\', $this->namespace).'\\'.$resolved;
                            // Check if it exists in current namespace
                            if (\class_exists($namespacedName, false) ||
                                \interface_exists($namespacedName, false) ||
                                \trait_exists($namespacedName, false)) {
                                return $namespacedName;
                            }
                        }

                        return $resolved;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall through to return original name
        }

        return $name;
    }

    /**
     * Log a message from a CodeCleaner pass.
     *
     * @param string $message Message text to display
     */
    public function log(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Get all logged messages from the last clean operation.
     *
     * @return string[] Array of message strings
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Determine whether code looks like an "action" vs "inspection".
     *
     * Actions (assignments, setters, etc.) should use concise output.
     * Inspections (variable reads, getters, etc.) should use full output.
     *
     * @param array $codeBuffer Array of code lines
     *
     * @return bool True if code looks like an action (use concise output)
     */
    public function codeLooksLikeAction(array $codeBuffer): bool
    {
        if (empty($codeBuffer)) {
            return false;
        }

        try {
            $stmts = $this->parser->parse('<?php '.\implode(\PHP_EOL, $codeBuffer).';');

            if (empty($stmts)) {
                return false;
            }

            $expr = \end($stmts);

            // Unwrap namespace if present
            if ($expr instanceof Namespace_) {
                if (empty($expr->stmts)) {
                    return false;
                }
                $expr = \end($expr->stmts);
            }

            // Unwrap Expression and Return_ nodes to get to the actual expression
            if ($expr instanceof Expression || $expr instanceof Return_) {
                $expr = $expr->expr;
            }

            if ($expr === null) {
                return false;
            }

            // Assignment operations are actions
            if ($expr instanceof Assign || $expr instanceof AssignOp || $expr instanceof AssignRef) {
                return true;
            }

            // Simple variable reads or property fetches are inspections
            if ($expr instanceof Variable ||
                $expr instanceof PropertyFetch ||
                $expr instanceof StaticPropertyFetch) {
                return false;
            }

            // Check for method calls that look like actions
            if ($this->isActionMethodCall($expr)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Fall back to default behavior if parsing fails
        }

        // Default: if we can't tell, it's not an action
        return false;
    }

    /**
     * Determine if a method call appears to be an action vs inspection.
     */
    private function isActionMethodCall(Expr $expr): bool
    {
        if (!$expr instanceof MethodCall && !$expr instanceof StaticCall) {
            return false;
        }

        $methodName = $expr->name;
        if ($methodName instanceof Node\Identifier) {
            $methodName = $methodName->toString();
        }

        if (!\is_string($methodName)) {
            return false;
        }

        // Common inspection method prefixes
        $inspectionPrefixes = [
            'get', 'find', 'fetch', 'load', 'read', 'retrieve',
            'is', 'has', 'can', 'should', 'count', 'exists',
            'to', 'as', // converters like toArray, asString
        ];

        foreach ($inspectionPrefixes as $prefix) {
            if ($this->hasMethodPrefix($methodName, $prefix)) {
                return false;
            }
        }

        // If it doesn't match an inspection pattern, assume it's an action
        return true;
    }

    /**
     * Check if a method name has a given prefix in camelCase or snake_case.
     *
     * @param string $methodName Original method name
     * @param string $prefix     Lowercase prefix to check
     */
    private function hasMethodPrefix(string $methodName, string $prefix): bool
    {
        if (\stripos($methodName, $prefix) !== 0) {
            return false;
        }

        $prefixLen = \strlen($prefix);

        // Exact match (e.g., "get", "is")
        if (\strlen($methodName) === $prefixLen) {
            return true;
        }

        $nextChar = $methodName[$prefixLen];

        // snake_case: prefix followed by underscore (e.g., "get_name", "is_valid")
        if ($nextChar === '_') {
            return true;
        }

        // camelCase: prefix followed by uppercase (e.g., "getName", "isValid")
        if (\ctype_upper($nextChar)) {
            return true;
        }

        return false;
    }

    /**
     * Lex and parse a block of code.
     *
     * @see Parser::parse
     *
     * @throws ParseErrorException for parse errors that can't be resolved by
     *                             waiting a line to see what comes next
     *
     * @return array|false A set of statements, or false if incomplete
     */
    protected function parse(string $code, bool $requireSemicolons = false)
    {
        try {
            return $this->parser->parse($code);
        } catch (\PhpParser\Error $e) {
            if ($this->parseErrorIsUnclosedString($e, $code)) {
                return false;
            }

            if ($this->parseErrorIsUnterminatedComment($e, $code)) {
                return false;
            }

            if ($this->parseErrorIsTrailingComma($e, $code)) {
                return false;
            }

            if (!$this->parseErrorIsEOF($e)) {
                throw ParseErrorException::fromParseError($e);
            }

            if ($requireSemicolons) {
                return false;
            }

            try {
                // Unexpected EOF, try again with an implicit semicolon
                return $this->parser->parse($code.';');
            } catch (\PhpParser\Error $e) {
                return false;
            }
        }
    }

    private function parseErrorIsEOF(\PhpParser\Error $e): bool
    {
        $msg = $e->getRawMessage();

        return ($msg === 'Unexpected token EOF') || (\strpos($msg, 'Syntax error, unexpected EOF') !== false);
    }

    /**
     * A special test for unclosed single-quoted strings.
     *
     * Unlike (all?) other unclosed statements, single quoted strings have
     * their own special beautiful snowflake syntax error just for
     * themselves.
     */
    private function parseErrorIsUnclosedString(\PhpParser\Error $e, string $code): bool
    {
        if ($e->getRawMessage() !== 'Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE') {
            return false;
        }

        try {
            $this->parser->parse($code."';");
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    private function parseErrorIsUnterminatedComment(\PhpParser\Error $e, string $code): bool
    {
        return $e->getRawMessage() === 'Unterminated comment';
    }

    private function parseErrorIsTrailingComma(\PhpParser\Error $e, string $code): bool
    {
        return ($e->getRawMessage() === 'A trailing comma is not allowed here') && (\substr(\rtrim($code), -1) === ',');
    }
}
