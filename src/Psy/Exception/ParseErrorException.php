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

/**
 * A "parse error" Exception for Psy.
 */
class ParseErrorException extends \PHPParser_Error implements Exception
{
    /**
     * Constructor!
     *
     * @param string $message (default: "")
     * @param int    $line    (default: -1)
     */
    public function __construct($message = "", $line = -1)
    {
        $message = sprintf('PHP Parse error: %s', $message);
        parent::__construct($message, $line);
    }

    /**
     * Create a ParseErrorException from a PHPParser Error.
     *
     * @param \PHPParser_Error $e
     *
     * @return ParseErrorException
     */
    public static function fromParseError(\PHPParser_Error $e)
    {
        return new self($e->getRawMessage(), $e->getRawLine());
    }
}
