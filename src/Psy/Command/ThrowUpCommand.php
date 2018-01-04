<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Context;
use Psy\ContextAware;
use Psy\Exception\ErrorException;
use Psy\Exception\ThrowUpException;
use Psy\Input\CodeArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Throw an exception out of the Psy Shell.
 */
class ThrowUpCommand extends Command implements ContextAware
{
    /**
     * Context instance (for ContextAware interface).
     *
     * @var Context
     */
    protected $context;

    /**
     * ContextAware interface.
     *
     * @param Context $context
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('throw-up')
            ->setDefinition([
                new CodeArgument('exception', CodeArgument::OPTIONAL, 'Exception or Error to throw.'),
            ])
            ->setDescription('Throw an exception or error out of the Psy Shell.')
            ->setHelp(
                <<<'HELP'
Throws an exception or error out of the current the Psy Shell instance.

By default it throws the most recent exception.

e.g.
<return>>>> throw-up</return>
<return>>>> throw-up $e</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException if there is no exception to throw
     * @throws ThrowUpException         because what else do you expect it to do?
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($code = $input->getArgument('exception')) {
            $orig = $this->getApplication()->execute($code, true);
        } else {
            $orig = $this->context->getLastException();
        }

        if ($orig instanceof \Error) {
            $orig = ErrorException::fromError($orig);
        }

        if (!$orig instanceof \Exception) {
            throw new \InvalidArgumentException('throw-up can only throw Exceptions and Errors');
        }

        throw new ThrowUpException($orig);
    }
}
