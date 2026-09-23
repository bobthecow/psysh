<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\ManualUpdater;

use Psy\ManualUpdater\GitHubChecker;
use Psy\Test\Fixtures\HttpsStream;
use Psy\Test\TestCase;

class GitHubCheckerTest extends TestCase
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
        $checker = new GitHubChecker('en', 'php', '4.0.0', 'en');
        $this->setLatestVersion($checker, '3.0.0');

        $this->assertTrue($checker->isLatest());
    }

    public function testIsLatestReturnsFalseWhenOlderVersion()
    {
        $checker = new GitHubChecker('en', 'php', '2.0.0', 'en');
        $this->setLatestVersion($checker, '3.0.0');

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
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testMissingReleaseAssetFailsOnlyWhenDownloadUrlIsRequested()
    {
        $manifestUrl = 'https://example.test/manifest.json';
        $release = [[
            'assets_url' => 'https://example.test/assets',
            'assets'     => [[
                'name'                 => 'manifest.json',
                'browser_download_url' => $manifestUrl,
            ]],
        ]];
        $manifest = [
            'manuals' => [[
                'lang'    => 'en',
                'format'  => 'php',
                'version' => '3.0.0',
            ]],
        ];

        HttpsStream::register([
            GitHubChecker::RELEASES_URL => \json_encode($release),
            $manifestUrl                => \json_encode($manifest),
        ]);

        try {
            $checker = new GitHubChecker('en', 'php', '3.0.0', 'en');
            $this->assertSame('3.0.0', $checker->getLatest());
            $this->assertTrue($checker->isLatest());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('No manual download found');
            $checker->getDownloadUrl();
        } finally {
            HttpsStream::restore();
        }
    }

    /**
     * @dataProvider languageChangeProvider
     */
    public function testLanguageChangeDetection($currentLang, $targetLang, $currentVersion, $latestVersion, $expected)
    {
        $checker = new GitHubChecker($targetLang, 'php', $currentVersion, $currentLang);

        $this->setLatestVersion($checker, $latestVersion);

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

        $this->setLatestVersion($checker, $latestVersion);

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

    private function setLatestVersion(GitHubChecker $checker, string $version): void
    {
        $property = (new \ReflectionClass($checker))->getProperty('latestVersion');
        if (\PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($checker, $version);
    }
}
