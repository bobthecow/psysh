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

class ErrorException extends \ErrorException implements Exception
{
    private $rawMessage;

    public function __construct($message = "", $code = 0, $severity = 1, $filename = null, $lineno = null, $previous = null)
    {
        $this->rawMessage = $message;

        if (!empty($filename) && strpos($filename, 'Psy/Loop') !== false) {
            $filename = null;
        }

        switch ($severity) {
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $type = 'warning';
                break;

            case E_STRICT:
                $type = 'Strict error';
                break;

            default:
                $type = 'error';
                break;
        }

        $message = sprintf('PHP %s:  %s %s%son line %d', $type, $message, $filename ? 'on ' : '', $filename, $lineno);
        parent::__construct($message, $code, $severity, $filename, $lineno, $previous);
    }

    public function getRawMessage()
    {
        return $this->rawMessage;
    }

    public static function throwException($errno, $errstr, $errfile, $errline)
    {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}
