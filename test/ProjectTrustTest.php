<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\ConfigPaths;
use Psy\Configuration;
use Psy\ProjectTrust;

class ProjectTrustTest extends TestCase
{
    private function getProjectTrust(): ProjectTrust
    {
        return new ProjectTrust(new ConfigPaths());
    }

    public function testDefaultMode()
    {
        $trust = $this->getProjectTrust();
        $this->assertSame(Configuration::PROJECT_TRUST_PROMPT, $trust->getMode());
    }

    public function testSetMode()
    {
        $trust = $this->getProjectTrust();

        $trust->setMode(Configuration::PROJECT_TRUST_ALWAYS);
        $this->assertSame(Configuration::PROJECT_TRUST_ALWAYS, $trust->getMode());

        $trust->setMode(Configuration::PROJECT_TRUST_NEVER);
        $this->assertSame(Configuration::PROJECT_TRUST_NEVER, $trust->getMode());

        $trust->setMode(Configuration::PROJECT_TRUST_PROMPT);
        $this->assertSame(Configuration::PROJECT_TRUST_PROMPT, $trust->getMode());
    }

    public function testSetModeFromEnvTrue()
    {
        $trust = $this->getProjectTrust();
        $trust->setModeFromEnv('true');
        $this->assertSame(Configuration::PROJECT_TRUST_ALWAYS, $trust->getMode());
    }

    public function testSetModeFromEnvOne()
    {
        $trust = $this->getProjectTrust();
        $trust->setModeFromEnv('1');
        $this->assertSame(Configuration::PROJECT_TRUST_ALWAYS, $trust->getMode());
    }

    public function testSetModeFromEnvFalse()
    {
        $trust = $this->getProjectTrust();
        $trust->setModeFromEnv('false');
        $this->assertSame(Configuration::PROJECT_TRUST_NEVER, $trust->getMode());
    }

    public function testSetModeFromEnvZero()
    {
        $trust = $this->getProjectTrust();
        $trust->setModeFromEnv('0');
        $this->assertSame(Configuration::PROJECT_TRUST_NEVER, $trust->getMode());
    }

    public function testSetModeFromEnvInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PSYSH_TRUST_PROJECT value');

        $trust = $this->getProjectTrust();
        $trust->setModeFromEnv('invalid');
    }

    public function testForceTrustClearsForceUntrust()
    {
        $trust = $this->getProjectTrust();
        $trust->setForceUntrust(true);
        $this->assertTrue($trust->getForceUntrust());

        $trust->setForceTrust(true);
        $this->assertTrue($trust->getForceTrust());
        $this->assertFalse($trust->getForceUntrust());
    }

    public function testForceUntrustClearsForceTrust()
    {
        $trust = $this->getProjectTrust();
        $trust->setForceTrust(true);
        $this->assertTrue($trust->getForceTrust());

        $trust->setForceUntrust(true);
        $this->assertTrue($trust->getForceUntrust());
        $this->assertFalse($trust->getForceTrust());
    }

    public function testGetProjectTrustStatusForceUntrust()
    {
        $trust = $this->getProjectTrust();
        $trust->setForceUntrust(true);
        $this->assertFalse($trust->getProjectTrustStatus('/some/root'));
    }

    public function testGetProjectTrustStatusNeverMode()
    {
        $trust = $this->getProjectTrust();
        $trust->setMode(Configuration::PROJECT_TRUST_NEVER);
        $this->assertFalse($trust->getProjectTrustStatus('/some/root'));
    }

    public function testGetProjectTrustStatusForceTrust()
    {
        $trust = $this->getProjectTrust();
        $trust->setForceTrust(true);
        $this->assertTrue($trust->getProjectTrustStatus('/some/root'));
    }

    public function testGetProjectTrustStatusAlwaysMode()
    {
        $trust = $this->getProjectTrust();
        $trust->setMode(Configuration::PROJECT_TRUST_ALWAYS);
        $this->assertTrue($trust->getProjectTrustStatus('/some/root'));
    }

    public function testGetProjectTrustStatusPromptReturnsNull()
    {
        $trust = $this->getProjectTrust();
        $this->assertNull($trust->getProjectTrustStatus('/some/unknown/root'));
    }

    public function testIsProjectTrustedWithAlwaysMode()
    {
        $trust = $this->getProjectTrust();
        $trust->setMode(Configuration::PROJECT_TRUST_ALWAYS);
        $this->assertTrue($trust->isProjectTrusted('/any/root'));
    }

    public function testIsProjectTrustedWithNeverMode()
    {
        $trust = $this->getProjectTrust();
        $trust->setMode(Configuration::PROJECT_TRUST_NEVER);
        $this->assertFalse($trust->isProjectTrusted('/any/root'));
    }

    public function testIsProjectTrustedWithForceTrust()
    {
        $trust = $this->getProjectTrust();
        $trust->setForceTrust(true);
        $this->assertTrue($trust->isProjectTrusted('/any/root'));
    }

    public function testIsProjectTrustedWithForceUntrust()
    {
        $trust = $this->getProjectTrust();
        $trust->setForceUntrust(true);
        $this->assertFalse($trust->isProjectTrusted('/any/root'));
    }

    public function testNormalizeProjectRoot()
    {
        $trust = $this->getProjectTrust();

        // Backslashes should be normalized to forward slashes
        $this->assertSame('/some/path', $trust->normalizeProjectRoot('/some/path'));

        // Real paths get resolved
        $tmpDir = \sys_get_temp_dir();
        $normalized = $trust->normalizeProjectRoot($tmpDir);
        $this->assertStringNotContainsString('\\', $normalized);
    }

    public function testGetProjectRoot()
    {
        $trust = $this->getProjectTrust();
        $root = $trust->getProjectRoot();

        // We're running in the psysh project, so there should be a project root
        $this->assertNotNull($root);
        $this->assertStringNotContainsString('\\', $root);
    }

    public function testGetLocalConfigRoot()
    {
        $trust = $this->getProjectTrust();
        $root = $trust->getLocalConfigRoot();

        // Should return cwd (normalized)
        $this->assertNotNull($root);
        $this->assertStringNotContainsString('\\', $root);
    }

    public function testHasComposerAutoloadFiles()
    {
        $trust = $this->getProjectTrust();

        $tmpDir = \sys_get_temp_dir().'/psysh_trust_autoload_'.\getmypid();
        $vendorDir = $tmpDir.'/vendor';
        @\mkdir($vendorDir, 0700, true);
        @\file_put_contents($vendorDir.'/autoload.php', "<?php\n");

        $this->assertTrue($trust->hasComposerAutoloadFiles($tmpDir));

        @\unlink($vendorDir.'/autoload.php');
        @\rmdir($vendorDir);
        @\rmdir($tmpDir);

        // A non-existent path should not
        $this->assertFalse($trust->hasComposerAutoloadFiles('/non/existent/path'));
    }

    /**
     * @group isolation-fail
     */
    public function testGetLocalPsyshProjectRoot()
    {
        $trust = $this->getProjectTrust();

        // The psysh project itself has composer.json with name psy/psysh
        $projectRoot = \realpath(__DIR__.'/..');
        $result = $trust->getLocalPsyshProjectRoot($projectRoot);

        // Should find it since we're in the psysh project with vendor/autoload.php
        if (@\is_file($projectRoot.'/vendor/autoload.php')) {
            $this->assertNotNull($result);
        } else {
            $this->assertNull($result);
        }
    }

    public function testTrustPersistenceAndRetrieval()
    {
        $tmpDir = \sys_get_temp_dir().'/psysh_trust_test_'.\getmypid();
        @\mkdir($tmpDir, 0700, true);

        try {
            $configPaths = new ConfigPaths(['configDir' => $tmpDir]);
            $trust = new ProjectTrust($configPaths);

            // Initially no trusted roots
            $this->assertSame([], $trust->getTrustedProjectRoots());

            // Trust a root
            $this->assertTrue($trust->saveTrustedProjectRoots(['/some/project']));

            // Should be retrievable
            $roots = $trust->getTrustedProjectRoots();
            $this->assertContains('/some/project', $roots);

            // Trust file should exist
            $this->assertNotNull($trust->getProjectTrustFilePath());
            $this->assertFileExists($trust->getProjectTrustFilePath());
        } finally {
            // Cleanup
            @\unlink($tmpDir.'/trusted_projects.json');
            @\rmdir($tmpDir);
        }
    }

    public function testSessionTrustFallback()
    {
        // Create a ConfigPaths with a non-writable config dir
        $configPaths = new ConfigPaths(['configDir' => '/non/writable/path']);
        $trust = new ProjectTrust($configPaths);

        // Suppress the E_USER_NOTICE from ConfigPaths::ensureDir
        @$trust->trustProjectRoot('/some/project');

        // The project should now be trusted for this session
        $this->assertTrue($trust->isProjectTrusted('/some/project'));
    }

    public function testShouldPromptForLocalPsyshBinaryDefaultsFalse()
    {
        $trust = $this->getProjectTrust();

        $this->withUntrustedProjectEnv(function () use ($trust) {
            $this->assertFalse($trust->shouldPromptForLocalPsyshBinary());
        });
    }

    public function testShouldPromptForLocalPsyshBinaryUsesServerVar()
    {
        $trust = $this->getProjectTrust();

        $this->withUntrustedProjectEnv(function () use ($trust) {
            $_SERVER['PSYSH_UNTRUSTED_PROJECT'] = '/tmp/project';
            $this->assertTrue($trust->shouldPromptForLocalPsyshBinary());
        });
    }

    public function testShouldPromptForLocalPsyshBinaryUsesEnvironmentVar()
    {
        $trust = $this->getProjectTrust();

        $this->withUntrustedProjectEnv(function () use ($trust) {
            \putenv('PSYSH_UNTRUSTED_PROJECT=/tmp/project');
            $this->assertTrue($trust->shouldPromptForLocalPsyshBinary());
        });
    }

    public function testGetLocalPsyshProjectRootFallsBackToEnvironmentVar()
    {
        $trust = $this->getProjectTrust();

        $this->withUntrustedProjectEnv(function () use ($trust) {
            \putenv('PSYSH_UNTRUSTED_PROJECT=/tmp/project');
            $this->assertSame('/tmp/project', $trust->getLocalPsyshProjectRoot('/non/existent/project'));
        });
    }

    private function withUntrustedProjectEnv(callable $callback): void
    {
        $hadServer = \array_key_exists('PSYSH_UNTRUSTED_PROJECT', $_SERVER);
        $serverValue = $hadServer ? $_SERVER['PSYSH_UNTRUSTED_PROJECT'] : null;

        $hadEnv = \array_key_exists('PSYSH_UNTRUSTED_PROJECT', $_ENV);
        $envValue = $hadEnv ? $_ENV['PSYSH_UNTRUSTED_PROJECT'] : null;

        $processEnv = \getenv('PSYSH_UNTRUSTED_PROJECT');

        unset($_SERVER['PSYSH_UNTRUSTED_PROJECT'], $_ENV['PSYSH_UNTRUSTED_PROJECT']);
        \putenv('PSYSH_UNTRUSTED_PROJECT');

        try {
            $callback();
        } finally {
            if ($hadServer) {
                $_SERVER['PSYSH_UNTRUSTED_PROJECT'] = $serverValue;
            } else {
                unset($_SERVER['PSYSH_UNTRUSTED_PROJECT']);
            }

            if ($hadEnv) {
                $_ENV['PSYSH_UNTRUSTED_PROJECT'] = $envValue;
            } else {
                unset($_ENV['PSYSH_UNTRUSTED_PROJECT']);
            }

            if ($processEnv === false) {
                \putenv('PSYSH_UNTRUSTED_PROJECT');
            } else {
                \putenv('PSYSH_UNTRUSTED_PROJECT='.$processEnv);
            }
        }
    }
}
