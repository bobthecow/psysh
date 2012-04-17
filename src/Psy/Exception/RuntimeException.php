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
