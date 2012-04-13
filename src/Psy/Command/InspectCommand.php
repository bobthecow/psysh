<?php

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Shell;
use Psy\ShellAware;
use Psy\Util\Inspector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InspectCommand extends Command implements ShellAware
{
    private $shell;

    public function setShell(Shell $shell)
    {
        $this->shell = $shell;
    }

    protected function configure()
    {
        $this
            ->setName('inspect')
            ->setAliases(array('dump'))
            ->setDefinition(array(
                new InputArgument('value', InputArgument::REQUIRED, 'Value to inspect'),

                new InputOption('methods',    'm',  InputOption::VALUE_NONE, 'Show public methods defined on the class.'),
                new InputOption('properties', 'p',  InputOption::VALUE_NONE, 'Show class variables.'),
                new InputOption('constants',  'c',  InputOption::VALUE_NONE, 'Show class constants.'),
                new InputOption('ivars',      'i',  InputOption::VALUE_NONE, 'Include instance variables (implies --properties).'),
                new InputOption('verbose',    'v',  InputOption::VALUE_NONE, 'Show private and protected class members.'),
                new InputOption('all',        null, InputOption::VALUE_NONE, 'Show all methods, variables and constants.'),
            ))
            ->setDescription('Inspect an object.')
            ->setHelp(<<<EOL
Inspect an object. By default, it shows the public methods defined on the object.

Additionally, it can show constants, protected and private methods, as well as
class variables and instance variables.
EOL
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $valueName  = $input->getArgument('value');
        $verbose    = $input->getOption('verbose');

        $getAll = $input->getOption('all');
        $ivars  = $input->getOption('ivars');

        $getConstants  = $getAll || $input->getOption('constants');
        $getProperties = $getAll || $ivars || $input->getOption('properties');
        $getMethods    = $getAll || !($getConstants || $getProperties) || $input->getOption('methods');

        $props = array();

        if (substr($valueName, 0, 1) == '$') {
            $value = $this->shell->getScopeVariable(substr($valueName, 1));

            if (!is_object($value)) {
                return $this->inspectScalar($value, $output);
            }

            if ($getAll) {
                $ivars = true;
            }
        } else {
            $value = $valueName;

            // handle class names
            if (!class_exists($value)) {
                throw new \InvalidArgumentException('Unknown class: '.$value);
            }
        }

        $props[] = $this->inspectClass($value);

        if ($getConstants) {
            $props[] = $this->inspect('getConstants', 'Constants', array($value));
        }

        if ($getMethods) {
            $props[] = $this->inspect('getMethods', 'Methods', array($value, $verbose));
        }

        if ($getProperties) {
            $props[] = $this->inspect('getProperties', 'Properties', array($value, $verbose, $ivars));
        }

        $output->writeln(implode(PHP_EOL, array_filter($props)));
    }

    protected function inspectScalar($value, $output)
    {
        $output->writeln(var_export($value, true));
    }

    protected function inspectClass($value)
    {
        $output = sprintf('<strong>%s</strong>', is_object($value) ? get_class($value) : $value);
        if (is_object($value)) {
            $output .= sprintf(' (%s)', spl_object_hash($value));
        }

        return $output;
    }

    protected function inspect($method, $title, $args)
    {
        if ($result = call_user_func_array(array('Psy\Util\Inspector', $method), $args)) {
            return sprintf('  <info>%s</info>: %s', $title, implode(', ', $this->formatVisibility($result)));
        }
    }

    private $visibilityMap = array(
        'private'   => 'urgent',
        'protected' => 'comment',
        'instance'  => 'info',
    );

    protected function formatVisibility($members)
    {
        ksort($members);

        $ret = array();
        foreach ($members as $name => $visibility) {
            if (isset($this->visibilityMap[$visibility])) {
                $ret[] = sprintf('<%s>%s</%s>', $this->visibilityMap[$visibility], $name, $this->visibilityMap[$visibility]);
            } else {
                $ret[] = $name;
            }
        }

        return $ret;
    }
}
