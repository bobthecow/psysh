<?php

namespace Psy\Command;

use Psy\Command\ReflectingCommand;
use Psy\Formatter\DocblockFormatter;
use Psy\Shell;
use Psy\ShellAware;
use Psy\Util\Docblock;
use Psy\Util\Documentor;
use Psy\Util\Inspector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read the documentation for an object, class, constant, method or property.
 */
class DocCommand extends ReflectingCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('doc')
            ->setAliases(array('rtfm'))
            ->setDefinition(array(
                new InputArgument('value', InputArgument::REQUIRED, 'Function, class, instance, constant, method or property to document.'),
            ))
            ->setDescription('Read the documentation for an object, class, constant, method or property.')
            ->setHelp(<<<EOL
Read the documentation for an object, class, constant, method or property.

It's awesome for well-documented code, not quite as awesome for native functions
and poorly documented code.

e.g.
<return>>>> doc \Psy\Command\DocCommand</return>
<return>>>> doc \Psy\Command\DocCommand::getDocumentation</return>
<return>>>> \$d = new \Psy\Command\DocCommand</return>
<return>>>> doc \$d->shell</return>
EOL
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($signature, $doc) = $this->getDocumentation($input->getArgument('value'));

        $output->writeln($signature->prettyPrint());
        $pretty = DocblockFormatter::format($doc);
        if (!empty($pretty)) {
            $output->writeln('');
            $output->writeln($pretty);
        }
    }

    /**
     * Get a code signature and documentation for a function, class or instance, constant, method or property.
     *
     * @see Psy\Util\Documentor::get
     *
     * @param string $valueName Function, class, variable, constant, method or property name.
     *
     * @return array (Signature, Docblock)
     */
    protected function getDocumentation($valueName)
    {
        list ($value, $member, $kind) = $this->getTarget($valueName);

        return Documentor::get($value, $member, $kind);
    }
}
