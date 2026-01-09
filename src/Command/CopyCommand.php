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

use Psy\Clipboard\ClipboardFactory;
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
    private Presenter $presenter;
    private ?Configuration $config = null;

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
     * Set the configuration instance.
     *
     * @param Configuration $config
     */
    public function setConfiguration(Configuration $config)
    {
        $this->config = $config;
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
        $presented = $this->presenter->present($value);

        $allowOsc52 = $this->config ? $this->config->useOsc52Clipboard() : false;
        $method = (new ClipboardFactory($allowOsc52))->create();
        if (!$method->copy($presented)) {
            $output->writeln('<error>Unable to copy value to clipboard.</error>');
        }

        $output->writeln('<info>Copied to clipboard.</info>');

        return 0;
    }

}
