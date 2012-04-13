<?php

namespace Psy\Exception;

use Psy\Exception\Exception;

class RuntimeException extends \RuntimeException implements Exception
{
    private $rawMessage;

    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        $this->rawMessage = $message;
        parent::__construct($message, $code, $previous);
    }

    public function getRawMessage()
    {
        return $this->rawMessage;
    }
}
