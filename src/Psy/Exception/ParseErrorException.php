<?php

namespace Psy\Exception;

use Psy\Exception\Exception;

class ParseErrorException extends \PHPParser_Error implements Exception
{
    public function __construct($message = "", $line = -1)
    {
        $message = sprintf('PHP Parse error: %s', $message);
        parent::__construct($message, $line);
    }

    public static function fromParseError(\PHPParser_Error $e)
    {
        return new self($e->getRawMessage(), $e->getRawLine());
    }
}
