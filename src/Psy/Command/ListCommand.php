<?php

namespace Psy\Command;

use Psy\Command\ReflectingCommand;
use Psy\Formatter\ObjectFormatter;
use Psy\Formatter\Signature\SignatureFormatter;
use Psy\Reflection\ReflectionInstanceProperty;
use Psy\Util\Documentor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends ReflectingCommand
{
    private static $specialVars = array('_', 'this');

    protected function configure()
    {
        $this
            ->setName('ls')
            ->setAliases(array('list', 'dir'))
            ->setDefinition(array(
                new InputArgument('value', InputArgument::OPTIONAL, 'The instance or class to list.', null),

                new InputOption('all',     'a', InputOption::VALUE_NONE, 'Include private and protected methods and properties.'),
                new InputOption('long',    'l', InputOption::VALUE_NONE, 'List in long format: includes class names and method signatures.'),
                new InputOption('verbose', 'v', InputOption::VALUE_NONE, 'Include object and method signatures.'),
            ))
            ->setDescription('List local, instance or class variables.')
            ->setHelp(<<<EOF
List all variables currently defined in the local scope.

If a value is passed, list properties and methods of that value. If a class name
is passed instead, list constants and methods available on that class.

e.g.
<return>>>> ls</return>
<return>>>> ls \$foo</return>
<return>>>> ls -al ReflectionClass</return>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $showLong = $input->getOption('long');
        $showAll  = $input->getOption('all');
        $verbose  = $input->getOption('verbose');

        if ($value = $input->getArgument('value')) {
            list($value, $reflector)  = $this->getTargetAndReflector($value, true);
            list($constants, $methods, $properties) = $this->listTarget($reflector, $value, $showAll);

            if ($showLong) {
                $this->printTargetLong($output, $reflector, $constants, $methods, $properties);
            } else {
                $this->printTarget($output, $reflector, $constants, $methods, $properties);
            }
        } else {
            $vars = $this->listScopeVars($showAll, $verbose);
            if ($showLong) {
                $this->printScopeVarsLong($output, $vars);
            } else {
                $this->printScopeVars($output, $vars);
            }
        }
    }

    private function printTarget(OutputInterface $output, \Reflector $reflector, array $constants, array $methods, array $properties) {
        if (!empty($constants)) {
            $output->writeln(sprintf('<strong>Constants</strong>: %s', implode(', ', array_keys($constants))));
        }

        $formattedMethods = array_map($this->getVisibilityFormatter(), $methods);
        if (!empty($methods)) {
            $output->writeln(sprintf('<strong>Methods</strong>: %s', implode(', ', $formattedMethods)));
        }

        $formattedProperties = array_map($this->getPropertyVisibilityFormatter(), $properties);
        if (!empty($properties)) {
            $output->writeln(sprintf('<strong>Properties</strong>: %s', implode(', ', $formattedProperties)));
        }
    }

    private function printTargetLong(OutputInterface $output, \Reflector $reflector, array $constants, array $methods, array $properties) {
        $pad = max(array_map('strlen', array_merge(array_keys($constants), array_keys($methods), array_keys($properties))));

        if (!empty($constants)) {
            $output->writeln('<strong>Constants:</strong>');
            foreach ($constants as $name => $value) {
                $output->writeln(sprintf("  <comment>%-${pad}s</comment>  %s", $name, var_export($value, true)));
            }
        }

        $vis = $this->getVisibilityFormatter();
        if (!empty($methods)) {
            $output->writeln('<strong>Methods:</strong>');
            foreach ($methods as $name => $value) {
                $output->writeln(sprintf("  %s  %s", $vis($value, $pad), SignatureFormatter::format($value)));
            }
        }

        $vis = $this->getPropertyVisibilityFormatter();
        if (!empty($properties)) {
            $output->writeln('<strong>Properties:</strong>');
            foreach ($properties as $name => $value) {
                $output->writeln(sprintf("  %s  %s", $vis($value, $pad), SignatureFormatter::format($value)));
            }
        }
    }

    private function printScopeVars(OutputInterface $output, array $vars)
    {
        $formatted = array_map(array(__CLASS__, 'printVarName'), array_keys($vars));
        $output->writeln(sprintf('<strong>Local variables</strong>: %s', implode(', ', $formatted)));
    }

    private function printScopeVarsLong(OutputInterface $output, array $vars) {
        $hashes = array();
        $output->writeln('<strong>Local variables:</strong>');
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
    }

    private function listTarget(\ReflectionClass $reflector, $value, $showAll)
    {
        $constants  = $reflector->getConstants();

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

        // hacketyhack
        if ($showAll) {
            $hack = json_decode(json_encode($value), true);
            if (is_array($hack)) {
                $allProperties = array_keys($hack);
                foreach ($allProperties as $name) {
                    if (!isset($properties[$name])) {
                        $properties[$name] = new ReflectionInstanceProperty($value, $name);
                    }
                }
            }
        }

        ksort($constants);
        ksort($methods);
        ksort($properties);

        return array($constants, $methods, $properties);
    }

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

        $names = array_keys($scopeVars);

        $ret = array();

        $maxName  = 0;
        $maxType = 0;
        foreach ($scopeVars as $name => $var) {
            $maxName = max($maxName, strlen($name));
            $maxType = max($maxType, strlen(self::getType($var, $verbose)));
        }

        foreach ($scopeVars as $name => $var) {
            if (!$showAll && in_array($name, self::$specialVars)) {
                continue;
            }

            $ret[$name] = array(self::printVarName($name, $maxName), self::printType($var, $maxType, $verbose), self::getHash($var));
        }

        return $ret;
    }

    private function getVisibilityFormatter()
    {
        return function($el, $pad = 0) {
            switch (true) {
                case $el->isPrivate():
                    return sprintf("<urgent>%-${pad}s</urgent>", $el->getName());
                case $el->isProtected():
                    return sprintf("<comment>%-${pad}s</comment>", $el->getName());
                default:
                    return sprintf("%-${pad}s", $el->getName());
            }
        };
    }

    private function getPropertyVisibilityFormatter()
    {
        return function($el, $pad = 0) {
            switch (true) {
                case $el instanceof ReflectionInstanceProperty:
                    return sprintf("<info>\$%-${pad}s</info>", $el->getName());
                case $el->isPrivate():
                    return sprintf("<urgent>\$%-${pad}s</urgent>", $el->getName());
                case $el->isProtected():
                    return sprintf("<comment>\$%-${pad}s</comment>", $el->getName());
                default:
                    return sprintf("\$%-${pad}s", $el->getName());
            }
        };
    }

    public static function printVarName($name, $max = 0)
    {
        return sprintf(in_array($name, self::$specialVars) ? "<urgent>\$%-${max}s</urgent>" : "\$%-${max}s", $name);
    }

    private static function getType($var, $verbose = false)
    {
        if (is_object($var)) {
            return $verbose ? ObjectFormatter::formatRef($var) : get_class($var);
        } else {
            return strtolower(gettype($var));
        }
    }

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

    private static function getHash($var)
    {
        if (is_object($var)) {
            return spl_object_hash($var);
        }
    }

    private static function formatHash($var)
    {
        if (is_object($var)) {
            return sprintf('[<aside>%s</aside>]', spl_object_hash($var));
        }
    }
}
