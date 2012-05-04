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
 * A break exception, used for halting the PsySH shell.
 */
class BreakException extends \Exception implements Exception
{
    private $rawMessage;

    /**
     * {@inheritdoc}
     */
    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        $this->rawMessage = $message;
        parent::__construct(sprintf('Exit:  %s', $message), $code, $previous);
    }

    /**
     * Return a raw (unformatted) version of the error message.
     *
     * @return string
     */
    public function getRawMessage()
    {
        return $this->rawMessage;
    }
}
