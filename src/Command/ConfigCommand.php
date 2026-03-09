<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Command\Config\AbstractConfigCommand as InternalConfigCommand;
use Psy\Command\Config\ConfigGetCommand;
use Psy\Command\Config\ConfigListCommand;
use Psy\Command\Config\ConfigSetCommand;
use Psy\Input\CodeArgument;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Inspect and update runtime-configurable settings for the current shell session.
 */
class ConfigCommand extends InternalConfigCommand
{
    private string $defaultHelp = '';

    protected function configure(): void
    {
        $this->defaultHelp = \implode("\n", [
            'Inspect or update runtime-configurable PsySH settings for the current session.',
            '',
            'e.g.',
            '<return>>>> config list</return>',
            '<return>>>> config get verbosity</return>',
            '<return>>>> config set verbosity debug</return>',
            '<return>>>> config set pager off</return>',
            '<return>>>> config set clipboardCommand auto</return>',
            '',
            'Runtime-configurable keys include '.$this->formatOptionNames([
                'verbosity',
                'useUnicode',
                'errorLoggingLevel',
                'clipboardCommand',
                'useOsc52Clipboard',
                'colorMode',
                'theme',
                'pager',
                'requireSemicolons',
                'semicolonsSuppressReturn',
                'useBracketedPaste',
                'useSuggestions',
            ]).'.',
        ]);

        $this
            ->setName('config')
            ->setDefinition([
                new InputArgument('action', InputArgument::OPTIONAL, 'Action: list, get, or set.', 'list'),
                new InputArgument('key', InputArgument::OPTIONAL, 'Runtime-configurable option to inspect or update.'),
                new CodeArgument('value', CodeArgument::OPTIONAL, 'New value when using `set`.'),
            ])
            ->setDescription('Inspect or update runtime-configurable PsySH settings for the current session.')
            ->setHelp($this->defaultHelp);
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasParameterOption(['--help', '-h'], true)) {
            $output->writeln($this->asTextForInput($input));

            return 0;
        }

        return parent::run($input, $output);
    }

    public function asTextForInput(InputInterface $input): string
    {
        $action = $this->getActionFromInput($input);

        if ($action === '') {
            return $this->asText();
        }

        $command = $this->createChildCommand($action);

        if ($command === null) {
            return $this->asText();
        }

        return $command->asTextForInput($this->createChildInput($command, $action, $this->rawArguments($input)));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = \strtolower((string) $input->getArgument('action'));
        $command = $this->createChildCommand($action);

        if ($command === null) {
            throw new \InvalidArgumentException(\sprintf('Unknown config action: %s. Expected list, get, or set.', $action));
        }

        return $command->run($this->createChildInput($command, $action, [
            $action,
            (string) $input->getArgument('key'),
            (string) $input->getArgument('value'),
        ]), $output);
    }

    /**
     * @param string[] $arguments
     */
    private function createChildInput(Command $command, string $action, array $arguments): ArrayInput
    {
        $parameters = [];

        switch ($action) {
            case 'get':
                if (isset($arguments[1]) && $arguments[1] !== '') {
                    $parameters['key'] = $arguments[1];
                }
                break;

            case 'set':
                if (isset($arguments[1]) && $arguments[1] !== '') {
                    $parameters['key'] = $arguments[1];
                }
                if (isset($arguments[2]) && $arguments[2] !== '') {
                    $parameters['value'] = $arguments[2];
                }
                break;
        }

        $input = new ArrayInput($parameters, $command->getDefinition());
        $input->setInteractive(false);

        return $input;
    }

    private function createChildCommand(string $action): ?Command
    {
        switch ($action) {
            case '':
            case 'list':
                $command = new ConfigListCommand();
                break;

            case 'get':
                $command = new ConfigGetCommand();
                break;

            case 'set':
                $command = new ConfigSetCommand();
                break;

            default:
                return null;
        }

        $command->setConfiguration($this->getConfig());
        $command->setApplication($this->getApplication());

        return $command;
    }

    private function getActionFromInput(InputInterface $input): string
    {
        $arguments = $this->rawArguments($input);

        return \strtolower($arguments[0] ?? '');
    }

    /**
     * Extract positional arguments from the raw input string.
     *
     * Symfony's Input classes don't expose raw tokens after parsing, so we
     * re-tokenize __toString() output to recover them for child command routing.
     *
     * @return string[]
     */
    private function rawArguments(InputInterface $input): array
    {
        if (!$input instanceof ArrayInput && !$input instanceof StringInput) {
            return [];
        }

        \preg_match_all('/"[^"]*"|\'[^\']*\'|\S+/', $input->__toString(), $matches);

        $arguments = [];

        foreach ($matches[0] as $token) {
            if ($token === '--') {
                break;
            }

            if ($token !== '' && $token[0] === '-') {
                continue;
            }

            $arguments[] = $this->trimQuotes($token);
        }

        if (($arguments[0] ?? null) === $this->getName()) {
            \array_shift($arguments);
        }

        return $arguments;
    }

    private function trimQuotes(string $token): string
    {
        $quote = $token[0] ?? '';

        if (($quote === '"' || $quote === '\'') && \substr($token, -1) === $quote) {
            return \substr($token, 1, -1);
        }

        return $token;
    }
}
