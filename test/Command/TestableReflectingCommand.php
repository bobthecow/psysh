<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\Command\ReflectingCommand;
use Psy\Input\CodeArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Concrete implementation of ReflectingCommand for testing.
 */
class TestableReflectingCommand extends ReflectingCommand
{
    protected function configure()
    {
        $this
            ->setName('test-reflect')
            ->setDefinition([
                new CodeArgument('target', CodeArgument::OPTIONAL, 'A target to reflect.'),
            ])
            ->setDescription('Test reflecting command.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }

    // Expose protected methods for testing
    public function doGetTarget(string $valueName): array
    {
        return $this->getTarget($valueName);
    }

    public function doResolveName(string $name, bool $includeFunctions = false): string
    {
        return $this->resolveName($name, $includeFunctions);
    }

    public function doGetTargetAndReflector(string $valueName, ?OutputInterface $output = null): array
    {
        return $this->getTargetAndReflector($valueName, $output);
    }

    public function doResolveCode(string $code)
    {
        return $this->resolveCode($code);
    }

    public function doGetScopeVariable(string $name)
    {
        return $this->getScopeVariable($name);
    }

    public function doGetScopeVariables(): array
    {
        return $this->getScopeVariables();
    }

    public function doSetCommandScopeVariables(\Reflector $reflector)
    {
        $this->setCommandScopeVariables($reflector);
    }
}
