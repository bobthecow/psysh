<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A compatibility shim for PHPUnit's TestCase, so we can use modern-ish exception expectations on
 * older PHPUnit versions.
 *
 * This should go away when we drop support for ... PHP 5.x?
 */

namespace Psy\Test;

trait ExpectExceptionForwardCompatibility
{
    public function expectException($expectation)
    {
        $this->setExpectedException($expectation);
    }

    public function expectExceptionMessage($message)
    {
        $this->setExpectedException($this->getExpectedException(), $message);
    }
}

trait ExpectExceptionMessagMatchesForwardCompatibility
{
    public function expectExceptionMessageMatches($regularExpression)
    {
        if (\method_exists(\PHPUnit\Framework\TestCase::class, 'expectExceptionMessageRegExp')) {
            parent::expectExceptionMessageRegExp($regularExpression);
        } else {
            $this->setExpectedExceptionRegExp($this->getExpectedException(), $regularExpression);
        }
    }
}

if (\method_exists(\PHPUnit\Framework\TestCase::class, 'expectException')) {
    if (\method_exists(\PHPUnit\Framework\TestCase::class, 'expectExceptionMessageMatches')) {
        abstract class TestCase extends \PHPUnit\Framework\TestCase
        {
            // No forward compatibility needed!
        }
    } else {
        abstract class TestCase extends \PHPUnit\Framework\TestCase
        {
            use ExpectExceptionMessagMatchesForwardCompatibility;
        }
    }
} else {
    abstract class TestCase extends \PHPUnit\Framework\TestCase
    {
        use ExpectExceptionForwardCompatibility;
        use ExpectExceptionMessagMatchesForwardCompatibility;
    }
}
