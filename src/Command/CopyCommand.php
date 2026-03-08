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

use Psy\Clipboard\ClipboardMethod;
use Psy\Clipboard\NullClipboardMethod;
use Psy\Configuration;
use Psy\Input\CodeArgument;
use Psy\VarDumper\Presenter;
use Psy\VarDumper\PresenterAware;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Copy an inspected value to the clipboard.
 */
class CopyCommand extends ReflectingCommand implements PresenterAware
{
    private ?Configuration $config = null;
    private Presenter $presenter;

    /**
     * Set the configuration instance.
     *
     * @param Configuration $config
     */
    public function setConfiguration(Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * PresenterAware interface.
     *
     * @param Presenter $presenter
     */
    public function setPresenter(Presenter $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('copy')
            ->setDefinition([
                new CodeArgument('expression', CodeArgument::OPTIONAL, 'Expression to inspect and copy.'),
            ])
            ->setDescription('Copy the inspected value to the clipboard.')
            ->setHelp(
                <<<'HELP'
                Copy the inspected value to the clipboard.

                When given:
                - an expression, copy the inspect result of the expression to the clipboard.
                - no arguments, copy the last evaluated result (<info>$_</info>) to the clipboard.

                e.g.
                <return>>>> copy new Foo()</return>
                <return>>>> copy User::all()->toArray()</return>
                <return>>>> copy</return>
                HELP
            );
    }

    /**
     * {@inheritdoc}
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expression = $input->getArgument('expression');
        $value = $expression === null ? $this->context->get('_') : $this->resolveCode($expression);
        $presented = $this->stripAnsi($this->presenter->present($value));
        if (!$this->getClipboardMethod()->copy($presented, $output)) {
            $output->writeln('<error>Unable to copy value to clipboard.</error>');

            return 1;
        }

        $output->writeln('<info>Copied to clipboard.</info>');

        return 0;
    }

    private function getClipboardMethod(): ClipboardMethod
    {
        return $this->config ? $this->config->getClipboard() : new NullClipboardMethod(false);
    }

    private function stripAnsi(string $value): string
    {
        $value = \preg_replace("/\x1b\\][^\x07]*(\x07|\x1b\\\\)/", '', $value);
        $value = \preg_replace("/\x1b\\[[0-9;?]*[A-Za-z]/", '', $value);

        return $value ?? '';
    }
}
