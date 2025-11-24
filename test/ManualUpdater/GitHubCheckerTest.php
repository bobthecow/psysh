<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\ManualUpdater;

use Psy\ManualUpdater\GitHubChecker;

class GitHubCheckerTest extends \Psy\Test\TestCase
{
    public function testIsLatestReturnsFalseWhenNoCurrentVersion()
    {
        $checker = new GitHubChecker('en', 'php', null, null);
        $this->assertFalse($checker->isLatest());
    }

    public function testIsLatestReturnsFalseWhenLanguageChanged()
    {
        $checker = new GitHubChecker('es', 'php', '3.0.0', 'en');
        $this->assertFalse($checker->isLatest());
    }

    public function testIsLatestReturnsTrueWhenSameLanguageAndNewerVersion()
    {
        // Create a partial mock that we can set internal state on
        $checker = new GitHubChecker('en', 'php', '4.0.0', 'en');

        // Use reflection to set the latestVersion directly
        $reflection = new \ReflectionClass($checker);
        $property = $reflection->getProperty('latestVersion');
        if (\PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($checker, '3.0.0');

        $this->assertTrue($checker->isLatest());
    }

    public function testIsLatestReturnsFalseWhenOlderVersion()
    {
        // Create a partial mock that we can set internal state on
        $checker = new GitHubChecker('en', 'php', '2.0.0', 'en');

        // Use reflection to set the latestVersion directly
        $reflection = new \ReflectionClass($checker);
        $property = $reflection->getProperty('latestVersion');
        if (\PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($checker, '3.0.0');

        $this->assertFalse($checker->isLatest());
    }

    public function testConstructorAcceptsAllParameters()
    {
        $checker = new GitHubChecker('fr', 'sqlite', '2.0.15', 'fr');
        $this->assertInstanceOf(GitHubChecker::class, $checker);
    }

    public function testConstructorAcceptsNullParameters()
    {
        $checker = new GitHubChecker('en', 'php', null, null);
        $this->assertInstanceOf(GitHubChecker::class, $checker);
    }

    /**
     * @dataProvider languageChangeProvider
     */
    public function testLanguageChangeDetection($currentLang, $targetLang, $currentVersion, $latestVersion, $expected)
    {
        $checker = new GitHubChecker($targetLang, 'php', $currentVersion, $currentLang);

        // Set the latest version using reflection
        $reflection = new \ReflectionClass($checker);
        $property = $reflection->getProperty('latestVersion');
        if (\PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($checker, $latestVersion);

        $this->assertEquals($expected, $checker->isLatest());
    }

    public static function languageChangeProvider()
    {
        return [
            'same language, same version'  => ['en', 'en', '3.0.0', '3.0.0', true],
            'same language, newer current' => ['en', 'en', '3.0.1', '3.0.0', true],
            'different language'           => ['en', 'fr', '3.0.0', '3.0.0', false],
            'null current language'        => [null, 'en', '3.0.0', '3.0.0', true],
            'en to es'                     => ['en', 'es', '3.0.0', '3.0.0', false],
            'ja to ja, older current'      => ['ja', 'ja', '2.0.0', '3.0.0', false],
        ];
    }

    /**
     * @dataProvider versionComparisonProvider
     */
    public function testVersionComparison($currentVersion, $latestVersion, $expected)
    {
        $checker = new GitHubChecker('en', 'php', $currentVersion, 'en');

        // Set the latest version using reflection
        $reflection = new \ReflectionClass($checker);
        $property = $reflection->getProperty('latestVersion');
        if (\PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($checker, $latestVersion);

        $this->assertEquals($expected, $checker->isLatest());
    }

    public static function versionComparisonProvider()
    {
        return [
            'equal versions'           => ['3.0.0', '3.0.0', true],
            'newer current'            => ['3.0.1', '3.0.0', true],
            'older current'            => ['3.0.0', '3.0.1', false],
            'major version difference' => ['2.0.0', '3.0.0', false],
            'patch version newer'      => ['3.0.1', '3.0.0', true],
        ];
    }
}
