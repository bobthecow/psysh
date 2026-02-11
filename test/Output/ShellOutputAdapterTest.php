<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Output;

use Psy\Output\ShellOutputAdapter;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @group isolation-fail
 */
class ShellOutputAdapterTest extends \Psy\Test\TestCase
{
    public function testOutputInterfaceMethodsUseUntypedParametersForSymfony34Compatibility()
    {
        $write = new \ReflectionMethod(ShellOutputAdapter::class, 'write');
        $this->assertFalse($write->getParameters()[1]->hasType());
        $this->assertFalse($write->getParameters()[2]->hasType());

        $writeln = new \ReflectionMethod(ShellOutputAdapter::class, 'writeln');
        $this->assertFalse($writeln->getParameters()[1]->hasType());

        $setVerbosity = new \ReflectionMethod(ShellOutputAdapter::class, 'setVerbosity');
        $this->assertFalse($setVerbosity->getParameters()[0]->hasType());

        $setDecorated = new \ReflectionMethod(ShellOutputAdapter::class, 'setDecorated');
        $this->assertFalse($setDecorated->getParameters()[0]->hasType());
    }

    public function testPageWithBufferedOutput()
    {
        $output = new BufferedOutput();
        $adapter = new ShellOutputAdapter($output);

        $adapter->page("one\ntwo");

        $this->assertSame("one\ntwo\n", $output->fetch());
    }

    public function testPageSupportsLineNumbersWithBufferedOutput()
    {
        $output = new BufferedOutput();
        $adapter = new ShellOutputAdapter($output);

        $adapter->page([5 => 'alpha', 9 => 'beta'], ShellOutputAdapter::NUMBER_LINES | OutputInterface::OUTPUT_RAW);

        $display = $output->fetch();
        $this->assertStringContainsString("5: alpha\n", $display);
        $this->assertStringContainsString("9: beta\n", $display);
    }

    public function testWritelnSupportsLineNumbersWithBufferedOutput()
    {
        $output = new BufferedOutput();
        $adapter = new ShellOutputAdapter($output);

        $adapter->writeln(['<info>hello</info>'], ShellOutputAdapter::NUMBER_LINES | OutputInterface::OUTPUT_RAW);

        $this->assertStringContainsString("0: <info>hello</info>\n", $output->fetch());
    }
}
