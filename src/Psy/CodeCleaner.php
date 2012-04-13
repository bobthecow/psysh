<?php

namespace Psy;

use Psy\Exception\FatalErrorException;
use Psy\Exception\ParseErrorException;
use Psy\Exception\RuntimeException;
use PHPParser_Lexer as Lexer;
use PHPParser_Node_Expr as Expression;
use PHPParser_Node_Expr_FuncCall as FuncCall;
use PHPParser_Node_Stmt_Return as ReturnStatement;
use PHPParser_Node_Expr_Variable as Variable;
use PHPParser_Parser as Parser;
use PHPParser_PrettyPrinter_Zend as Printer;

// TODO: make namespaces kinda work
// TODO: catch as many fatal errors as possible
//  * "PHP Fatal error:  Function name must be a string" (e.g. `$foo()` when there's no $foo in scope)

class CodeCleaner
{
    private $parser;

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
    }

    public function clean(array $codeLines)
    {
        $code = "<?php " . implode(PHP_EOL, $codeLines);

        try {
            $stmts = $this->parse($code);
        } catch (\PHPParser_Error $e) {
            if ($e->getRawMessage() !== "Unexpected token EOF") {
                throw ParseErrorException::fromParseError($e);
            }

            try {
                // Unexpected EOF, try again with an implicit semicolon
                $stmts = $this->parse($code.';');
            } catch (\PHPParser_Error $e) {
                return false;
            }
        }

        // Catch fatal errors before they happen
        $this->validateStatements($stmts);

        // Add an implicit return if the last statement was an expression...
        $last = end($stmts);
        if ($last instanceof Expression) {
            $stmts[count($stmts) - 1] = new ReturnStatement($last, $last->getLine());
        }

        return $this->printer->prettyPrint($stmts);
    }

    protected function parse($code)
    {
        return $this->parser->parse(new Lexer($code));
    }

    protected function validateStatements($stmts)
    {
        if (!is_array($stmts) || $stmts instanceof \Traversable) {
            throw new \InvalidArgumentException('Unable to validate non-traversable sets.');
        }

        foreach ($stmts as $stmt) {
            $this->validateFunctionCalls($stmt);
            $this->validateLeavePsyshAlone($stmt);

            if (is_object($stmt) && $stmt instanceof \Traversable) {
                $this->validateStatements(iterator_to_array($stmt));
            }
        }
    }

    protected function validateFunctionCalls($stmt)
    {
        if ($stmt instanceof FuncCall) {
            $name = $stmt->name;
            // if function name is an expression, give it a pass for now.
            // see TODO about fixing possible fatal errors.
            if (!$name instanceof Expression) {
                $name = implode('\\', $name->parts);
                if (!function_exists($name)) {
                    $message = sprintf('Call to undefined function %s()', $name);
                    throw new FatalErrorException($message, 0, 1, null, $stmt->getLine());
                }
            }
        }
    }

    protected function validateLeavePsyshAlone($stmt)
    {
        if ($stmt instanceof Variable && $stmt->name === "__psysh__") {
            throw new RuntimeException('Don\'t mess with $__psysh__. Bad things will happen.');
        }
    }
}
