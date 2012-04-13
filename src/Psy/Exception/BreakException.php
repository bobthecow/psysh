<?php

namespace Psy\Exception;

use Psy\Exception\Exception;

class BreakException extends \Exception implements Exception
{
    private $rawMessage;

    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        $this->rawMessage = $message;
        parent::__construct(sprintf('Exit:  %s', $message), $code, $previous);
    }

    public function getRawMessage()
    {
        return $this->rawMessage;
    }
}
