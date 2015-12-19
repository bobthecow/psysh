<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Exception\RuntimeException;
use Psy\Formatter\CodeFormatter;
use Psy\Formatter\SignatureFormatter;
use Psy\Output\ShellOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show the code for an object, class, constant, method or property.
 */
class ShowCommand extends ReflectingCommand
{
    private $forceColor;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('show')
            ->setDefinition(array(
                new InputArgument('value', InputArgument::REQUIRED, 'Function, class, instance, constant, method or property to show.'),
            ))
            ->setDescription('Show the code for an object, class, constant, method or property.')
            ->setHelp(
                <<<HELP
Show the code for an object, class, constant, method or property.

e.g.
<return>>>> show \$myObject</return>
<return>>>> show Psy\Shell::debug</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($value, $reflector) = $this->getTargetAndReflector($input->getArgument('value'));

        try {
            $output->page(CodeFormatter::format($reflector, $this->forceColor()), ShellOutput::OUTPUT_RAW);
        } catch (RuntimeException $e) {
            $output->writeln(SignatureFormatter::format($reflector));
            throw $e;
        }
    }

    /**
     * Sets whether or not to force colors in output.
     *
     * @param bool $forceColor
     */
    public function setForceColor($forceColor)
    {
        $this->forceColor = $forceColor;
    }

    /**
     * Returns whether or not to force colors in output.
     *
     * @return bool
     */
    public function forceColor()
    {
        return $this->forceColor;
    }
}
