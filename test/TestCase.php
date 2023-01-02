<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A compatibility shim for PHPUnit's TestCase, so we can use modern-ish exception expectations on
 * older PHPUnit versions.
 *
 * @todo Remove shims whenever we update minimum PHPUnit versions.
 */

namespace Psy\Test;

// PHPUnit <= 9
trait AssertMatchesRegularExpressionForwardCompatibility
{
    public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = '')
    {
        static::assertRegExp($pattern, $string, $message);
    }
}

// PHPUnit <= 8
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

// PHPUnit <= 7
trait AssertContainsForwardCompatibility
{
    public static function assertStringContainsString($needle, $haystack, $message = '')
    {
        static::assertContains((string) $needle, (string) $haystack, $message);
    }

    public static function assertStringContainsStringIgnoringCase($needle, $haystack, $message = '')
    {
        static::assertContains((string) $needle, (string) $haystack, $message, true);
    }

    public static function assertStringNotContainsString($needle, $haystack, $message = '')
    {
        static::assertNotContains((string) $needle, (string) $haystack, $message);
    }

    public static function assertStringNotContainsStringIgnoringCase($needle, $haystack, $message = '')
    {
        static::assertNotContains((string) $needle, (string) $haystack, $message, true);
    }
}

if (\method_exists(\PHPUnit\Framework\TestCase::class, 'assertStringContainsString')) {
    if (\method_exists(\PHPUnit\Framework\TestCase::class, 'expectExceptionMessageMatches')) {
        if (\method_exists(\PHPUnit\Framework\TestCase::class, 'assertMatchesRegularExpression')) {
            abstract class TestCase extends \PHPUnit\Framework\TestCase
            {
                // No forward compatibility needed!
            }
        } else {
            abstract class TestCase extends \PHPUnit\Framework\TestCase
            {
                use AssertMatchesRegularExpressionForwardCompatibility;
            }
        }
    } else {
        abstract class TestCase extends \PHPUnit\Framework\TestCase
        {
            use AssertMatchesRegularExpressionForwardCompatibility;
            use ExpectExceptionMessagMatchesForwardCompatibility;
        }
    }
} else {
    abstract class TestCase extends \PHPUnit\Framework\TestCase
    {
        use AssertContainsForwardCompatibility;
        use AssertMatchesRegularExpressionForwardCompatibility;
        use ExpectExceptionMessagMatchesForwardCompatibility;
    }
}
