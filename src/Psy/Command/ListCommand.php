<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Command\ReflectingCommand;
use Psy\Formatter\ObjectFormatter;
use Psy\Reflection\ReflectionConstant;
use Psy\Exception\RuntimeException;
use Psy\Formatter\SignatureFormatter;
use Psy\Output\ShellOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List available local variables, object properties, etc.
 */
class ListCommand extends ReflectingCommand
{
    private static $specialVars = array('_');

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ls')
            ->setAliases(array('list', 'dir'))
            ->setDefinition(array(
                new InputArgument('value', InputArgument::OPTIONAL, 'A class or instance to list.', null),

                new InputOption('locals',     '',  InputOption::VALUE_NONE, 'Display local variables.'),
                new InputOption('globals',    'g', InputOption::VALUE_NONE, 'Display global variables.'),

                new InputOption('constants',  'c', InputOption::VALUE_NONE, 'Display constants.'),
                new InputOption('properties', 'p', InputOption::VALUE_NONE, 'Display properties (public properties by default).'),
                new InputOption('methods',    'm', InputOption::VALUE_NONE, 'Display methods (public methods by default).'),

                new InputOption('all',        'a', InputOption::VALUE_NONE, 'Include private and protected methods and properties.'),
                new InputOption('long',       'l', InputOption::VALUE_NONE, 'List in long format: includes class names and method signatures.'),
                new InputOption('verbose',    'v', InputOption::VALUE_NONE, 'Include object and method signatures.'),
            ))
            ->setDescription('List local, instance or class variables, methods and constants.')
            ->setHelp(<<<EOF
List all variables currently defined in the local scope.

If a value is passed, list properties, constants and methods of that value. If a
class name is passed instead, list constants and methods available on that class.

e.g.
<return>>>> ls</return>
<return>>>> ls \$foo</return>
<return>>>> ls -al ReflectionClass</return>
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);

        $showAll  = $input->getOption('all');
        $showLong = $input->getOption('long');
        $verbose  = $input->getOption('verbose');

        if ($value = $input->getArgument('value')) {
            $showConstants  = $input->getOption('constants');
            $showProperties = $input->getOption('properties');
            $showMethods    = $input->getOption('methods');

            // if none is specified, list all.
            if (!$showConstants && !$showProperties && !$showMethods) {
                $showConstants = $showProperties = $showMethods = true;
            }

            list($value, $reflector)  = $this->getTargetAndReflector($value, true);
            if (!$reflector instanceof \ReflectionClass) {
                throw new RuntimeException('List command expects an object or class');
            }

            list($constants, $methods, $properties) = $this->listTarget($reflector, $value, $showAll);

            $method = $showLong ? 'printTargetLong' : 'printTarget';
            $this->$method(
                $output,
                $reflector,
                $showConstants  ? $constants  : array(),
                $showMethods    ? $methods    : array(),
                $showProperties ? $properties : array()
            );
        } else {
            $showLocals  = $input->getOption('locals');
            $showGlobals = $input->getOption('globals');

            $method = $showLong ? 'printScopeVarsLong' : 'printScopeVars';

            if ($showGlobals) {
                $this->$method($output, $this->listGlobalVars($verbose), 'Global');
            }

            if ($showLocals || !$showGlobals) {
                $this->$method($output, $this->listScopeVars($showAll, $verbose));
            }
        }
    }

    /**
     * Validate that input options make sense.
     *
     * @throws RuntimeException if options are inconsistent.
     *
     * @param InputInterface $input
     */
    private function validateInput(InputInterface $input)
    {
        if ($input->getArgument('value')) {
            foreach (array('locals', 'globals') as $option) {
                if ($input->getOption($option)) {
                    throw new RuntimeException('--' . $option . ' does not make sense with a specified object.');
                }
            }
        } else {
            foreach (array('constants', 'properties', 'methods') as $option) {
                if ($input->getOption($option)) {
                    throw new RuntimeException('--' . $option . ' does not make sense without a specified object.');
                }
            }
        }

    }

    /**
     * Print a formatted version of the reflector target to $output.
     *
     * @param ShellOutput $output
     * @param \Reflector  $reflector
     * @param array       $constants
     * @param array       $methods
     * @param array       $properties
     */
    private function printTarget(ShellOutput $output, \Reflector $reflector, array $constants, array $methods, array $properties)
    {
        if (!empty($constants)) {
            $output->writeln(sprintf('<strong>Constants</strong>: %s', implode(', ', array_keys($constants))));
        }

        if (!empty($methods)) {
            $formattedMethods = array_map($this->getVisibilityFormatter(), $methods);
            $output->writeln(sprintf('<strong>Methods</strong>: %s', implode(', ', $formattedMethods)));
        }

        if (!empty($properties)) {
            $formattedProperties = array_map($this->getVisibilityFormatter('$'), $properties);
            $output->writeln(sprintf('<strong>Properties</strong>: %s', implode(', ', $formattedProperties)));
        }
    }

    /**
     * Print a _long_ formatted version of the reflector target to $output.
     *
     * @param ShellOutput $output
     * @param \Reflector  $reflector
     * @param array       $constants
     * @param array       $methods
     * @param array       $properties
     */
    private function printTargetLong(ShellOutput $output, \Reflector $reflector, array $constants, array $methods, array $properties)
    {
        $methodVis   = $this->getVisibilityFormatter();
        $propertyVis = $this->getVisibilityFormatter('$');

        $output->page(function($output) use ($constants, $methods, $properties, $methodVis, $propertyVis) {
            $pad = empty($properties) ? 0 : (max(array_map('strlen', array_keys($properties))) + 1);
            if (!empty($methods) || !empty($constants)) {
                $pad = max($pad, max(array_map('strlen', array_merge(array_keys($constants), array_keys($methods)))));
            }

            if (!empty($constants)) {
                $output->writeln('<strong>Constants:</strong>');
                foreach ($constants as $name => $value) {
                    $output->writeln(sprintf("  <comment>%-${pad}s</comment>  %s", $name, SignatureFormatter::format($value)));
                }
            }

            if (!empty($methods)) {
                $output->writeln('<strong>Methods:</strong>');
                foreach ($methods as $name => $value) {
                    $output->writeln(sprintf("  %s  %s", $methodVis($value, $pad), SignatureFormatter::format($value)));
                }
            }

            if (!empty($properties)) {
                $output->writeln('<strong>Properties:</strong>');
                foreach ($properties as $name => $value) {
                    $output->writeln(sprintf("  %s  %s", $propertyVis($value, $pad - 1), SignatureFormatter::format($value)));
                }
            }
        });
    }

    /**
     * Print a formatted version of local variables to $output.
     *
     * @param ShellOutput $output
     * @param array       $vars
     */
    private function printScopeVars(ShellOutput $output, array $vars, $scope = 'Local')
    {
        $formatted = array_map(array(__CLASS__, 'printVarName'), array_keys($vars));
        $output->writeln(sprintf('<strong>%s variables</strong>: %s', $scope, implode(', ', $formatted)));
    }

    /**
     * Print a _long_ formatted version of local variables to $output.
     *
     * @param ShellOutput $output
     * @param array       $vars
     */
    private function printScopeVarsLong(ShellOutput $output, array $vars, $scope = 'Local')
    {
        $output->page(function($output) use($vars, $scope) {
            $hashes = array();
            $output->writeln('<strong>' . $scope . ' variables:</strong>');
            foreach ($vars as $name => $var) {
                if (isset($var[2])) {
                    if (isset($hashes[$var[2]])) {
                        $var[] = sprintf('-> <return>$%s</return>', $hashes[$var[2]]);
                    } else {
                        $hashes[$var[2]] = $name;
                    }

                    unset($var[2]);
                }
                $output->writeln('  '.implode(' ', $var));
            }
        });
    }

    /**
     * Get constants, methods and properties for the $reflector target.
     *
     * @param \ReflectionClass $reflector
     * @param mixed            $value
     * @param mixed            $showAll
     *
     * @return array Array of arrays of strings: constants, methods, properties
     */
    private function listTarget(\ReflectionClass $reflector, $value, $showAll)
    {
        $constants  = array();
        foreach ($reflector->getConstants() as $name => $v) {
            $constants[$name] = new ReflectionConstant($reflector, $name);
        }

        $methods    = array();
        foreach ($reflector->getMethods() as $method) {
            if ($showAll || $method->isPublic()) {
                $methods[$method->getName()] = $method;
            }
        }

        $properties = array();
        foreach ($reflector->getProperties() as $name => $property) {
            if ($showAll || $property->isPublic()) {
                $properties[$property->getName()] = $property;
            }
        }

        ksort($constants);
        ksort($methods);
        ksort($properties);

        return array($constants, $methods, $properties);
    }

    /**
     * List scope variables
     *
     * @param bool $showAll
     * @param bool $verbose (default: false)
     *
     * @return array A list of scope variables.
     */
    private function listScopeVars($showAll, $verbose = false)
    {
        $scopeVars = $this->getScopeVariables();
        uksort($scopeVars, function($a, $b) {
            if ($a == '_') {
                return 1;
            } elseif ($b == '_') {
                return -1;
            } else {
                return strcasecmp($a, $b);
            }
        });

        $ret = array();

        list($maxName, $maxType) = $this->getMaxVarLengths($scopeVars, $verbose);
        foreach ($scopeVars as $name => $var) {
            if (!$showAll && in_array($name, self::$specialVars)) {
                continue;
            }

            $ret[$name] = array(self::printVarName($name, $maxName), self::printType($var, $maxType, $verbose), self::getHash($var));
        }

        return $ret;
    }

    /**
     * List global variables
     *
     * @param bool $verbose (default: false)
     *
     * @return array A list of global variables.
     */
    private function listGlobalVars($verbose = false)
    {
        global $GLOBALS;

        $scopeVars = $GLOBALS;
        ksort($scopeVars);

        $ret = array();

        list($maxName, $maxType) = $this->getMaxVarLengths($scopeVars, $verbose);
        foreach ($scopeVars as $name => $var) {
            $ret[$name] = array(self::printVarName($name, $maxName, true), self::printType($var, $maxType, $verbose), self::getHash($var));
        }

        return $ret;
    }

    /**
     * Get the longest variable name and type name.
     *
     * @param array   $scopeVars
     * @param boolean $verbose
     *
     * @return array(int, int)
     */
    private function getMaxVarLengths($scopeVars, $verbose)
    {
        $maxName  = 0;
        $maxType = 0;
        foreach ($scopeVars as $name => $var) {
            $maxName = max($maxName, strlen($name) + 1);
            $maxType = max($maxType, strlen(self::getType($var, $verbose)));
        }
    }

    /**
     * Get a visibility formatter callback.
     *
     * @param string $prefix (default: '')
     *
     * @return Closure
     */
    private function getVisibilityFormatter($prefix = '')
    {
        return function($el, $pad = 0) use ($prefix) {
            switch (true) {
                case $el->isPrivate():
                    return sprintf("<urgent>%s%-${pad}s</urgent>", $prefix, $el->getName());
                case $el->isProtected():
                    return sprintf("<comment>%s%-${pad}s</comment>", $prefix, $el->getName());
                default:
                    return sprintf("%s%-${pad}s", $prefix, $el->getName());
            }
        };
    }

    /**
     * Format a variable name for output.
     *
     * @param string  $name
     * @param int     $max      Padding (default: 0)
     * @param boolean $isGlobal
     *
     * @return string
     */
    public static function printVarName($name, $max = 0, $isGlobal = false)
    {
        return sprintf(($isGlobal || in_array($name, self::$specialVars)) ? "<urgent>\$%-${max}s</urgent>" : "\$%-${max}s", $name);
    }

    /**
     * Get a string representation of a variable type.
     *
     * @param mixed $var
     * @param bool  $verbose (default: false)
     *
     * @return string
     */
    private static function getType($var, $verbose = false)
    {
        if (is_object($var)) {
            return $verbose ? ObjectFormatter::formatRef($var) : get_class($var);
        } else {
            return strtolower(gettype($var));
        }
    }

    /**
     * Format a string representation of a variable type for output.
     *
     * @param mixed $var
     * @param int   $max     Padding (default: 0)
     * @param bool  $verbose (default: false)
     *
     * @return string
     */
    private static function printType($var, $max = 0, $verbose = false)
    {
        $val = self::getType($var, $verbose);
        if (is_object($var)) {
            if (!$verbose) {
                $val = sprintf('<%s>', $val);
            }
            $format = 'comment';
        } else {
            $val = sprintf('(%s)', $val);
            $format = 'info';
        }

        return sprintf('<%s>%s</%s>%s', $format, $val, $format, str_repeat(' ', max(0, $max - strlen($val))));
    }

    /**
     * Get a string representation of an object (hash)
     *
     * @param mixed $var
     *
     * @return string
     */
    private static function getHash($var)
    {
        if (is_object($var)) {
            return spl_object_hash($var);
        }
    }
}
