<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use PHPParser_Lexer as Lexer;
use PHPParser_NodeTraverser as NodeTraverser;
use PHPParser_Parser as Parser;
use PHPParser_PrettyPrinter_Default as Printer;
use Psy\CodeCleaner\AbstractClassPass;
use Psy\CodeCleaner\AssignThisVariablePass;
use Psy\CodeCleaner\CallPass;
use Psy\CodeCleaner\CallTimePassByReferencePass;
use Psy\CodeCleaner\FunctionReturnInWriteContextPass;
use Psy\CodeCleaner\ImplicitReturnPass;
use Psy\CodeCleaner\InstanceOfPass;
use Psy\CodeCleaner\LeavePsyshAlonePass;
use Psy\CodeCleaner\MagicConstantsPass;
use Psy\CodeCleaner\NamespacePass;
use Psy\CodeCleaner\StaticConstructorPass;
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
     * @param Parser        $parser    A PHPParser Parser instance. One will be created if not explicitly supplied.
     * @param Printer       $printer   A PHPParser Printer instance. One will be created if not explicitly supplied.
     * @param NodeTraverser $traverser A PHPParser NodeTraverser instance. One will be created if not explicitly supplied.
     */
    public function __construct(Parser $parser = null, Printer $printer = null, NodeTraverser $traverser = null)
    {
        $this->parser    = $parser    ?: new Parser(new Lexer);
        $this->printer   = $printer   ?: new Printer;
        $this->traverser = $traverser ?: new NodeTraverser;

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
            new AbstractClassPass,
            new AssignThisVariablePass,
            new FunctionReturnInWriteContextPass,
            new CallTimePassByReferencePass,
            new InstanceOfPass,
            new LeavePsyshAlonePass,
            new ImplicitReturnPass,
            new UseStatementPass,      // must run before namespace and validation passes
            new NamespacePass($this),  // must run after the implicit return pass
            new StaticConstructorPass,
            new ValidFunctionNamePass,
            new ValidClassNamePass,
            new ValidConstantPass,
            new MagicConstantsPass,
            new CallPass,              // must run after the valid function name pass
        );
    }

    /**
     * Clean the given array of code.
     *
     * @throws ParseErrorException if the code is invalid PHP, and cannot be coerced into valid PHP.
     *
     * @param array $codeLines
     *
     * @return string|false Cleaned PHP code, False if the input is incomplete.
     */
    public function clean(array $codeLines)
    {
        $stmts = $this->parse("<?php " . implode(PHP_EOL, $codeLines) . PHP_EOL);
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
     * @param string $code
     *
     * @return array A set of statements
     */
    protected function parse($code)
    {
        try {
            return $this->parser->parse($code);
        } catch (\PHPParser_Error $e) {
            if (!$this->parseErrorIsEOF($e)) {
                throw ParseErrorException::fromParseError($e);
            }

            try {
                // Unexpected EOF, try again with an implicit semicolon
                return $this->parser->parse($code.';');
            } catch (\PHPParser_Error $e) {
                return false;
            }
        }
    }

    private function parseErrorIsEOF(\PHPParser_Error $e)
    {
        $msg = $e->getRawMessage();

        return ($msg === "Unexpected token EOF") || (strpos($msg, "Syntax error, unexpected EOF") !== false);
    }
}
