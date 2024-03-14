<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

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

/**
 * A service to clean up user input, detect parse errors before they happen,
 * and generally work around issues with the PHP code evaluation experience.
 */
class CodeCleaner
{
    private $yolo = false;
    private $strictTypes = false;

    private $parser;
    private $printer;
    private $traverser;
    private $namespace;

    /**
     * CodeCleaner constructor.
     *
     * @param Parser|null        $parser      A PhpParser Parser instance. One will be created if not explicitly supplied
     * @param Printer|null       $printer     A PhpParser Printer instance. One will be created if not explicitly supplied
     * @param NodeTraverser|null $traverser   A PhpParser NodeTraverser instance. One will be created if not explicitly supplied
     * @param bool               $yolo        run without input validation
     * @param bool               $strictTypes enforce strict types by default
     */
    public function __construct(?Parser $parser = null, ?Printer $printer = null, ?NodeTraverser $traverser = null, bool $yolo = false, bool $strictTypes = false)
    {
        $this->yolo = $yolo;
        $this->strictTypes = $strictTypes;

        $this->parser = $parser ?? (new ParserFactory())->createParser();
        $this->printer = $printer ?: new Printer();
        $this->traverser = $traverser ?: new NodeTraverser();

        foreach ($this->getDefaultPasses() as $pass) {
            $this->traverser->addVisitor($pass);
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
        if ($this->yolo) {
            return $this->getYoloPasses();
        }

        $useStatementPass = new UseStatementPass();
        $namespacePass = new NamespacePass($this);

        // Try to add implicit `use` statements and an implicit namespace,
        // based on the file in which the `debug` call was made.
        $this->addImplicitDebugContext([$useStatementPass, $namespacePass]);

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
            new LeavePsyshAlonePass(),
            new ListPass(),
            new LoopContextPass(),
            new PassableByReferencePass(),
            new ReturnTypePass(),
            new EmptyArrayDimFetchPass(),
            new ValidConstructorPass(),

            // Rewriting shenanigans
            $useStatementPass,        // must run before the namespace pass
            new ExitPass(),
            new ImplicitReturnPass(),
            new MagicConstantsPass(),
            $namespacePass,           // must run after the implicit return pass
            new RequirePass(),
            new StrictTypesPass($this->strictTypes),

            // Namespace-aware validation (which depends on aforementioned shenanigans)
            new ValidClassNamePass(),
            new ValidFunctionNamePass(),
        ];
    }

    /**
     * A set of code cleaner passes that don't try to do any validation, and
     * only do minimal rewriting to make things work inside the REPL.
     *
     * This list should stay in sync with the "rewriting shenanigans" in
     * getDefaultPasses above.
     *
     * @return CodeCleanerPass[]
     */
    private function getYoloPasses(): array
    {
        $useStatementPass = new UseStatementPass();
        $namespacePass = new NamespacePass($this);

        // Try to add implicit `use` statements and an implicit namespace,
        // based on the file in which the `debug` call was made.
        $this->addImplicitDebugContext([$useStatementPass, $namespacePass]);

        return [
            new LeavePsyshAlonePass(),
            $useStatementPass,        // must run before the namespace pass
            new ExitPass(),
            new ImplicitReturnPass(),
            new MagicConstantsPass(),
            $namespacePass,           // must run after the implicit return pass
            new RequirePass(),
            new StrictTypesPass($this->strictTypes),
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
     *
     * @param array|null $namespace (default: null)
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
     * Lex and parse a block of code.
     *
     * @see Parser::parse
     *
     * @throws ParseErrorException for parse errors that can't be resolved by
     *                             waiting a line to see what comes next
     *
     * @param string $code
     * @param bool   $requireSemicolons
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
     *
     * @param \PhpParser\Error $e
     * @param string           $code
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

    private function parseErrorIsUnterminatedComment(\PhpParser\Error $e, $code): bool
    {
        return $e->getRawMessage() === 'Unterminated comment';
    }

    private function parseErrorIsTrailingComma(\PhpParser\Error $e, $code): bool
    {
        return ($e->getRawMessage() === 'A trailing comma is not allowed here') && (\substr(\rtrim($code), -1) === ',');
    }
}
