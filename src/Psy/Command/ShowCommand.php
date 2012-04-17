<?php

namespace Psy\Command;

use Psy\Command\ReflectingCommand;
use Psy\Formatter\CodeFormatter;
use Psy\Formatter\Signature\SignatureFormatter;
use Psy\Output\ShellOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show the code for an object, class, constant, method or property.
 */
class ShowCommand extends ReflectingCommand
{
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
            ->setHelp(<<<EOL
Show the code for an object, class, constant, method or property.
EOL
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($value, $reflector) = $this->getTargetAndReflector($input->getArgument('value'));

        $output->page(function(ShellOutput $output) use ($reflector) {
            $output->writeln(SignatureFormatter::format($reflector));
            $output->writeln(CodeFormatter::format($reflector), ShellOutput::OUTPUT_RAW);
        });
    }
}
