<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PHPParser_Lexer as Lexer;
use PHPParser_NodeTraverser as NodeTraverser;
use PHPParser_Parser as Parser;
use PHPParser_PrettyPrinter_Default as Printer;
use Psy\CodeCleaner\CodeCleanerPass;
use Psy\Exception\ParseErrorException;

class CodeCleanerTestCase extends \PHPUnit_Framework_TestCase
{
    protected $pass;
    protected $traverser;
    private $parser;
    private $printer;

    protected function setPass(CodeCleanerPass $pass)
    {
        $this->pass = $pass;
        if (!isset($this->traverser)) {
            $this->traverser = new NodeTraverser();
        }
        $this->traverser->addVisitor($this->pass);
    }

    protected function parse($code, $prefix = '<?php ')
    {
        $code = $prefix . $code;
        try {
            return $this->getParser()->parse($code);
        } catch (\PHPParser_Error $e) {
            if (!$this->parseErrorIsEOF($e)) {
                throw ParseErrorException::fromParseError($e);
            }

            try {
                // Unexpected EOF, try again with an implicit semicolon
                return $this->getParser()->parse($code . ';');
            } catch (\PHPParser_Error $e) {
                return false;
            }
        }
    }

    protected function traverse(array $stmts)
    {
        return $this->traverser->traverse($stmts);
    }

    protected function prettyPrint(array $stmts)
    {
        return $this->getPrinter()->prettyPrint($stmts);
    }

    protected function assertProcessesAs($from, $to)
    {
        $stmts = $this->parse($from);
        $stmts = $this->traverse($stmts);
        $this->assertEquals($to, $this->prettyPrint($stmts));
    }

    private function getParser()
    {
        if (!isset($this->parser)) {
            $this->parser = new Parser(new Lexer());
        }

        return $this->parser;
    }

    private function getPrinter()
    {
        if (!isset($this->printer)) {
            $this->printer = new Printer();
        }

        return $this->printer;
    }

    private function parseErrorIsEOF(\PHPParser_Error $e)
    {
        $msg = $e->getRawMessage();

        return ($msg === "Unexpected token EOF") || (strpos($msg, "Syntax error, unexpected EOF") !== false);
    }
}
