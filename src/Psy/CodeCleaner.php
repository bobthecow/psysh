<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use PHPParser_Lexer as Lexer;
use PHPParser_Parser as Parser;
use PHPParser_PrettyPrinter_Zend as Printer;
use Psy\CodeCleaner\ImplicitReturnPass;
use Psy\CodeCleaner\LeavePsyshAlonePass;
use Psy\CodeCleaner\NamespacePass;
use Psy\CodeCleaner\ValidClassNamePass;
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
    private $passes;
    private $namespace;

    /**
     * CodeCleaner constructor.
     *
     * @param Parser  $parser  A PHPParser Parser instance. One will be created if not explicitly supplied.
     * @param Printer $printer A PHPParser Printer instance. One will be created if not explicitly supplied.
     */
    public function __construct(Parser $parser = null, Printer $printer = null)
    {
        if ($parser === null) {
            $parser = new Parser;
        }

        if ($printer === null) {
            $printer = new Printer;
        }

        $this->parser  = $parser;
        $this->printer = $printer;

        $this->passes = $this->getDefaultPasses();
    }

    /**
     * Get default CodeCleaner passes.
     *
     * @return array
     */
    private function getDefaultPasses()
    {
        return array(
            new LeavePsyshAlonePass,
            new ImplicitReturnPass,
            new NamespacePass($this), // must run after the implicit return pass
            new ValidFunctionNamePass,
            new ValidClassNamePass,
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
        $stmts = $this->parse("<?php " . implode(PHP_EOL, $codeLines));
        if ($stmts === false) {
            return false;
        }

        // Catch fatal errors before they happen
        foreach ($this->passes as $pass) {
            $pass->process($stmts);
        }

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
            return $this->parser->parse(new Lexer($code));
        } catch (\PHPParser_Error $e) {
            if ($e->getRawMessage() !== "Unexpected token EOF") {
                throw ParseErrorException::fromParseError($e);
            }

            try {
                // Unexpected EOF, try again with an implicit semicolon
                return $this->parser->parse(new Lexer($code.';'));
            } catch (\PHPParser_Error $e) {
                return false;
            }
        }
    }
}
