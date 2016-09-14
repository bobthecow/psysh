<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
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
use Psy\CodeCleaner\ExitPass;
use Psy\CodeCleaner\FunctionReturnInWriteContextPass;
use Psy\CodeCleaner\ImplicitReturnPass;
use Psy\CodeCleaner\InstanceOfPass;
use Psy\CodeCleaner\LeavePsyshAlonePass;
use Psy\CodeCleaner\LegacyEmptyPass;
use Psy\CodeCleaner\MagicConstantsPass;
use Psy\CodeCleaner\NamespacePass;
use Psy\CodeCleaner\PassableByReferencePass;
use Psy\CodeCleaner\StaticConstructorPass;
use Psy\CodeCleaner\StrictTypesPass;
use Psy\CodeCleaner\UseStatementPass;
use Psy\CodeCleaner\ValidClassNamePass;
use Psy\CodeCleaner\ValidConstantPass;
use Psy\CodeCleaner\ValidFunctionNamePass;
use Psy\Exception\ParseErrorException;

/**
 * A service to clean up user input, detect parse errors before they happen,
 * and generally work around issues with the PHP code evaluation experience.
 */
class CodeCleaner
{
    private $parser;
    private $printer;
    private $traverser;
    private $namespace;

    /**
     * CodeCleaner constructor.
     *
     * @param Parser        $parser    A PhpParser Parser instance. One will be created if not explicitly supplied
     * @param Printer       $printer   A PhpParser Printer instance. One will be created if not explicitly supplied
     * @param NodeTraverser $traverser A PhpParser NodeTraverser instance. One will be created if not explicitly supplied
     */
    public function __construct(Parser $parser = null, Printer $printer = null, NodeTraverser $traverser = null)
    {
        if ($parser === null) {
            $parserFactory = new ParserFactory();
            $parser        = $parserFactory->createParser();
        }

        $this->parser    = $parser;
        $this->printer   = $printer ?: new Printer();
        $this->traverser = $traverser ?: new NodeTraverser();

        foreach ($this->getDefaultPasses() as $pass) {
            $this->traverser->addVisitor($pass);
        }
    }

    /**
     * Get default CodeCleaner passes.
     *
     * @return array
     */
    private function getDefaultPasses()
    {
        return array(
            new AbstractClassPass(),
            new AssignThisVariablePass(),
            new FunctionReturnInWriteContextPass(),
            new CallTimePassByReferencePass(),
            new PassableByReferencePass(),
            new CalledClassPass(),
            new InstanceOfPass(),
            new LeavePsyshAlonePass(),
            new LegacyEmptyPass(),
            new ImplicitReturnPass(),
            new UseStatementPass(),      // must run before namespace and validation passes
            new NamespacePass($this),    // must run after the implicit return pass
            new StrictTypesPass(),
            new StaticConstructorPass(),
            new ValidFunctionNamePass(),
            new ValidClassNamePass(),
            new ValidConstantPass(),
            new MagicConstantsPass(),
            new ExitPass(),
        );
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
    public function clean(array $codeLines, $requireSemicolons = false)
    {
        $stmts = $this->parse('<?php ' . implode(PHP_EOL, $codeLines) . PHP_EOL, $requireSemicolons);
        if ($stmts === false) {
            return false;
        }

        // Catch fatal errors before they happen
        $stmts = $this->traverser->traverse($stmts);

        return $this->printer->prettyPrint($stmts);
    }

    /**
     * Set the current local namespace.
     *
     * @param null|array $namespace (default: null)
     *
     * @return null|array
     */
    public function setNamespace(array $namespace = null)
    {
        $this->namespace = $namespace;
    }

    /**
     * Get the current local namespace.
     *
     * @return null|array
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
    protected function parse($code, $requireSemicolons = false)
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

            if (!$this->parseErrorIsEOF($e)) {
                throw ParseErrorException::fromParseError($e);
            }

            if ($requireSemicolons) {
                return false;
            }

            try {
                // Unexpected EOF, try again with an implicit semicolon
                return $this->parser->parse($code . ';');
            } catch (\PhpParser\Error $e) {
                return false;
            }
        }
    }

    private function parseErrorIsEOF(\PhpParser\Error $e)
    {
        $msg = $e->getRawMessage();

        return ($msg === 'Unexpected token EOF') || (strpos($msg, 'Syntax error, unexpected EOF') !== false);
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
     *
     * @return bool
     */
    private function parseErrorIsUnclosedString(\PhpParser\Error $e, $code)
    {
        if ($e->getRawMessage() !== 'Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE') {
            return false;
        }

        try {
            $this->parser->parse($code . "';");
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    private function parseErrorIsUnterminatedComment(\PhpParser\Error $e, $code)
    {
        return $e->getRawMessage() === 'Unterminated comment';
    }
}
