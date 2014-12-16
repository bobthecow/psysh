<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Exception\RuntimeException;
use Psy\Presenter\Presenter;
use Psy\Presenter\PresenterManager;
use Psy\Presenter\PresenterManagerAware;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dump an object or primitive.
 *
 * This is like var_dump but *way* awesomer.
 */
class DumpCommand extends ReflectingCommand implements PresenterManagerAware
{
    private $presenterManager;

    /**
     * PresenterManagerAware interface.
     *
     * @param PresenterManager $manager
     */
    public function setPresenterManager(PresenterManager $manager)
    {
        $this->presenterManager = $manager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dump')
            ->setDefinition(array(
                new InputArgument('target', InputArgument::REQUIRED, 'A target object or primitive to dump.', null),
                new InputOption('depth', '', InputOption::VALUE_REQUIRED, 'Depth to parse', 10),
                new InputOption('all', 'a', InputOption::VALUE_NONE, 'Include private and protected methods and properties.'),
            ))
            ->setDescription('Dump an object or primitive.')
            ->setHelp(
                <<<HELP
Dump an object or primitive.

This is like var_dump but <strong>way</strong> awesomer.

e.g.
<return>>>> dump \$_</return>
<return>>>> dump \$someVar</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $depth  = $input->getOption('depth');
        $target = $this->resolveTarget($input->getArgument('target'));
        $output->page($this->presenterManager->present($target, $depth, true, $input->getOption('all') ? Presenter::VERBOSE : null));
    }

    /**
     * Resolve dump target name.
     *
     * @throws RuntimeException if target name does not exist in the current scope.
     *
     * @param string $target
     *
     * @return mixed
     */
    protected function resolveTarget($target)
    {
        $matches = array();
        if (preg_match(self::INSTANCE, $target, $matches)) {
            return $this->getScopeVariable($matches[1]);
        } else {
            throw new RuntimeException('Unknown target: ' . $target);
        }
    }
}
