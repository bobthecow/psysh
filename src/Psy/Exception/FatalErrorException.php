<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Exception;

use Psy\Exception\Exception;

class FatalErrorException extends \ErrorException implements Exception
{
    private $rawMessage;

    public function __construct($message = "", $code = 0, $severity = 9000, $filename = null, $lineno = null, $previous = null)
    {
        $this->rawMessage = $message;
        $message = sprintf('PHP Fatal error:  %s in %s on line %d', $message, $filename ?: "eval()'d code", $lineno);
        parent::__construct($message, $code, $severity, $filename, $lineno, $previous);
    }

    public function getRawMessage()
    {
        return $this->rawMessage;
    }
}
