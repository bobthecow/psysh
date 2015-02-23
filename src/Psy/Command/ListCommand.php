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

use Psy\Command\ListCommand\ClassConstantEnumerator;
use Psy\Command\ListCommand\ClassEnumerator;
use Psy\Command\ListCommand\ConstantEnumerator;
use Psy\Command\ListCommand\FunctionEnumerator;
use Psy\Command\ListCommand\GlobalVariableEnumerator;
use Psy\Command\ListCommand\InterfaceEnumerator;
use Psy\Command\ListCommand\MethodEnumerator;
use Psy\Command\ListCommand\PropertyEnumerator;
use Psy\Command\ListCommand\TraitEnumerator;
use Psy\Command\ListCommand\VariableEnumerator;
use Psy\Exception\RuntimeException;
use Psy\Presenter\PresenterManager;
use Psy\Presenter\PresenterManagerAware;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List available local variables, object properties, etc.
 */
class ListCommand extends ReflectingCommand implements PresenterManagerAware
{
    protected $presenterManager;
    protected $enumerators;

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
            ->setName('ls')
            ->setAliases(array('list', 'dir'))
            ->setDefinition(array(
                new InputArgument('target', InputArgument::OPTIONAL, 'A target class or object to list.', null),

                new InputOption('vars',        '',  InputOption::VALUE_NONE,     'Display variables.'),
                new InputOption('constants',   'c', InputOption::VALUE_NONE,     'Display defined constants.'),
                new InputOption('functions',   'f', InputOption::VALUE_NONE,     'Display defined functions.'),
                new InputOption('classes',     'k', InputOption::VALUE_NONE,     'Display declared classes.'),
                new InputOption('interfaces',  'I', InputOption::VALUE_NONE,     'Display declared interfaces.'),
                new InputOption('traits',      't', InputOption::VALUE_NONE,     'Display declared traits.'),

                new InputOption('properties',  'p', InputOption::VALUE_NONE,     'Display class or object properties (public properties by default).'),
                new InputOption('methods',     'm', InputOption::VALUE_NONE,     'Display class or object methods (public methods by default).'),

                new InputOption('grep',        'G', InputOption::VALUE_REQUIRED, 'Limit to items matching the given pattern (string or regex).'),
                new InputOption('insensitive', 'i', InputOption::VALUE_NONE,     'Case-insensitive search (requires --grep).'),
                new InputOption('invert',      'v', InputOption::VALUE_NONE,     'Inverted search (requires --grep).'),

                new InputOption('globals',     'g', InputOption::VALUE_NONE,     'Include global variables.'),
                new InputOption('internal',    'n', InputOption::VALUE_NONE,     'Limit to internal functions and classes.'),
                new InputOption('user',        'u', InputOption::VALUE_NONE,     'Limit to user-defined constants, functions and classes.'),
                new InputOption('category',    'C', InputOption::VALUE_REQUIRED, 'Limit to constants in a specific category (e.g. "date").'),

                new InputOption('all',         'a', InputOption::VALUE_NONE,     'Include private and protected methods and properties.'),
                new InputOption('long',        'l', InputOption::VALUE_NONE,     'List in long format: includes class names and method signatures.'),
            ))
            ->setDescription('List local, instance or class variables, methods and constants.')
            ->setHelp(
                <<<HELP
List variables, constants, classes, interfaces, traits, functions, methods,
and properties.

Called without options, this will return a list of variables currently in scope.

If a target object is provided, list properties, constants and methods of that
target. If a class, interface or trait name is passed instead, list constants
and methods on that class.

e.g.
<return>>>> ls</return>
<return>>>> ls \$foo</return>
<return>>>> ls -k --grep mongo -i</return>
<return>>>> ls -al ReflectionClass</return>
<return>>>> ls --constants --category date</return>
<return>>>> ls -l --functions --grep /^array_.*/</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);
        $this->initEnumerators();

        $method = $input->getOption('long') ? 'writeLong' : 'write';

        if ($target = $input->getArgument('target')) {
            list($target, $reflector) = $this->getTargetAndReflector($target, true);
        } else {
            $reflector = null;
        }

        // TODO: something cleaner than this :-/
        if ($input->getOption('long')) {
            $output->startPaging();
        }

        foreach ($this->enumerators as $enumerator) {
            $this->$method($output, $enumerator->enumerate($input, $reflector, $target));
        }

        if ($input->getOption('long')) {
            $output->stopPaging();
        }
    }

    /**
     * Initialize Enumerators.
     */
    protected function initEnumerators()
    {
        if (!isset($this->enumerators)) {
            $mgr = $this->presenterManager;

            $this->enumerators = array(
                new ClassConstantEnumerator($mgr),
                new ClassEnumerator($mgr),
                new ConstantEnumerator($mgr),
                new FunctionEnumerator($mgr),
                new GlobalVariableEnumerator($mgr),
                new InterfaceEnumerator($mgr),
                new PropertyEnumerator($mgr),
                new MethodEnumerator($mgr),
                new TraitEnumerator($mgr),
                new VariableEnumerator($mgr, $this->context),
            );
        }
    }

    /**
     * Write the list items to $output.
     *
     * @param OutputInterface $output
     * @param null|array      $result List of enumerated items.
     */
    protected function write(OutputInterface $output, array $result = null)
    {
        if ($result === null) {
            return;
        }

        foreach ($result as $label => $items) {
            $names = array_map(array($this, 'formatItemName'), $items);
            $output->writeln(sprintf('<strong>%s</strong>: %s', $label, implode(', ', $names)));
        }
    }

    /**
     * Write the list items to $output.
     *
     * Items are listed one per line, and include the item signature.
     *
     * @param OutputInterface $output
     * @param null|array      $result List of enumerated items.
     */
    protected function writeLong(OutputInterface $output, array $result = null)
    {
        if ($result === null) {
            return;
        }

        $table = $this->getTable($output);

        foreach ($result as $label => $items) {
            $output->writeln('');
            $output->writeln(sprintf('<strong>%s:</strong>', $label));

            $table->setRows(array());
            foreach ($items as $item) {
                $table->addRow(array($this->formatItemName($item), $item['value']));
            }

            if ($table instanceof TableHelper) {
                $table->render($output);
            } else {
                $table->render();
            }
        }
    }

    /**
     * Format an item name given its visibility.
     *
     * @param array $item
     *
     * @return string
     */
    private function formatItemName($item)
    {
        return sprintf('<%s>%s</%s>', $item['style'], OutputFormatter::escape($item['name']), $item['style']);
    }

    /**
     * Validate that input options make sense, provide defaults when called without options.
     *
     * @throws RuntimeException if options are inconsistent.
     *
     * @param InputInterface $input
     */
    private function validateInput(InputInterface $input)
    {
        // grep, invert and insensitive
        if (!$input->getOption('grep')) {
            foreach (array('invert', 'insensitive') as $option) {
                if ($input->getOption($option)) {
                    throw new RuntimeException('--' . $option . ' does not make sense without --grep');
                }
            }
        }

        if (!$input->getArgument('target')) {
            // if no target is passed, there can be no properties or methods
            foreach (array('properties', 'methods') as $option) {
                if ($input->getOption($option)) {
                    throw new RuntimeException('--' . $option . ' does not make sense without a specified target.');
                }
            }

            foreach (array('globals', 'vars', 'constants', 'functions', 'classes', 'interfaces', 'traits') as $option) {
                if ($input->getOption($option)) {
                    return;
                }
            }

            // default to --vars if no other options are passed
            $input->setOption('vars', true);
        } else {
            // if a target is passed, classes, functions, etc don't make sense
            foreach (array('vars', 'globals', 'functions', 'classes', 'interfaces', 'traits') as $option) {
                if ($input->getOption($option)) {
                    throw new RuntimeException('--' . $option . ' does not make sense with a specified target.');
                }
            }

            foreach (array('constants', 'properties', 'methods') as $option) {
                if ($input->getOption($option)) {
                    return;
                }
            }

            // default to --constants --properties --methods if no other options are passed
            $input->setOption('constants',  true);
            $input->setOption('properties', true);
            $input->setOption('methods',    true);
        }
    }
}
