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

use Psy\Configuration;
use Psy\Readline\InteractiveReadline;

class FunctionsTest extends TestCase
{
    public function testInfoReportsInteractiveReadlineDetails()
    {
        $config = new Configuration([
            'configFile' => __DIR__.'/Fixtures/empty.php',
        ]);

        $historyFile = \tempnam(\sys_get_temp_dir(), 'psysh-test-info-history-');
        $resetConfig = new Configuration([
            'configFile' => __DIR__.'/Fixtures/empty.php',
        ]);

        try {
            $config->setHistoryFile($historyFile);
            $config->setReadline(new InteractiveReadline($historyFile));
            $config->setUseExperimentalReadline(true);
            $config->setUseSuggestions(true);
            $config->setUseTabCompletion(true);

            \Psy\info($config);
            $info = \Psy\info();

            $this->assertSame(InteractiveReadline::class, $info['readline']['readline service']);
            $this->assertTrue($info['readline']['interactive readline requested']);
            $this->assertTrue($info['readline']['syntax highlighting']);
            $this->assertArrayNotHasKey('interactive readline supported', $info['readline']);

            $this->assertSame('jsonl', $info['history']['history format']);

            $this->assertTrue($info['autocomplete']['tab completion enabled']);
            $this->assertSame('interactive readline', $info['autocomplete']['completion integration']);
            $this->assertTrue($info['autocomplete']['inline suggestions']);
            $this->assertArrayNotHasKey('syntax highlighting', $info['autocomplete']);
        } finally {
            \Psy\info($resetConfig);
            @\unlink($historyFile);
        }
    }

    public function testInfoUsesBuiltinThemeNameShorthand()
    {
        $config = new Configuration([
            'configFile' => __DIR__.'/Fixtures/empty.php',
            'theme'      => 'compact',
        ]);
        $resetConfig = new Configuration([
            'configFile' => __DIR__.'/Fixtures/empty.php',
        ]);

        try {
            \Psy\info($config);
            $info = \Psy\info();

            $this->assertSame('compact', $info['output']['theme']);
        } finally {
            \Psy\info($resetConfig);
        }
    }

    public function testInfoKeepsExpandedThemeConfigForCustomThemes()
    {
        $config = new Configuration([
            'configFile' => __DIR__.'/Fixtures/empty.php',
            'theme'      => [
                'compact' => true,
                'prompt'  => '$ ',
            ],
        ]);
        $resetConfig = new Configuration([
            'configFile' => __DIR__.'/Fixtures/empty.php',
        ]);

        try {
            \Psy\info($config);
            $info = \Psy\info();

            $this->assertSame([
                'compact'      => true,
                'prompt'       => '$ ',
                'bufferPrompt' => '. ',
                'replayPrompt' => '- ',
                'returnValue'  => '= ',
            ], $info['output']['theme']);
        } finally {
            \Psy\info($resetConfig);
        }
    }
}
