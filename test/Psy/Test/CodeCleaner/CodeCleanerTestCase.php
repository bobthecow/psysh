<?php

namespace Psy\Test\CodeCleaner;

use PHPParser_Lexer as Lexer;
use PHPParser_Parser as Parser;
use PHPParser_PrettyPrinter_Zend as Printer;
use Psy\Exception\ParseErrorException;

class CodeCleanerTestCase extends \PHPUnit_Framework_TestCase
{
    protected $pass;
    private $parser;
    private $printer;

    protected function parse($code, $prefix = '<?php ')
    {
        $code = $prefix.$code;
        try {
            return $this->getParser()->parse($code);
        } catch (\PHPParser_Error $e) {
            if ($e->getRawMessage() !== "Unexpected token EOF") {
                throw ParseErrorException::fromParseError($e);
            }

            try {
                // Unexpected EOF, try again with an implicit semicolon
                return $this->getParser()->parse($code.';');
            } catch (\PHPParser_Error $e) {
                return false;
            }
        }
    }

    protected function prettyPrint(array $stmts)
    {
        return $this->getPrinter()->prettyPrint($stmts);
    }

    protected function assertProcessesAs($from, $to)
    {
        $stmts = $this->parse($from);
        $this->pass->process($stmts);
        $this->assertEquals($to, $this->prettyPrint($stmts));
    }

    private function getParser()
    {
        if (!isset($this->parser)) {
            $this->parser = new Parser(new Lexer);
        }

        return $this->parser;
    }

    private function getPrinter()
    {
        if (!isset($this->printer)) {
            $this->printer = new Printer;
        }

        return $this->printer;
    }
}
