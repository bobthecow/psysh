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

use Psy\Configuration;
use Psy\Formatter\DocblockFormatter;
use Psy\Formatter\ManualFormatter;
use Psy\Formatter\SignatureFormatter;
use Psy\Input\CodeArgument;
use Psy\ManualUpdater\ManualUpdate;
use Psy\Output\ShellOutput;
use Psy\Reflection\ReflectionConstant;
use Psy\Reflection\ReflectionLanguageConstruct;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read the documentation for an object, class, constant, method or property.
 */
class DocCommand extends ReflectingCommand
{
    const INHERIT_DOC_TAG = '{@inheritdoc}';

    private ?Configuration $config = null;

    /**
     * Set the configuration instance.
     *
     * @param \Psy\Configuration $config
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
            ->setName('doc')
            ->setAliases(['rtfm', 'man'])
            ->setDefinition([
                new InputOption('all', 'a', InputOption::VALUE_NONE, 'Show documentation for superclasses as well as the current class.'),
                new InputOption('update-manual', null, InputOption::VALUE_OPTIONAL, 'Download and install the latest PHP manual (optional language code)', false),
                new CodeArgument('target', CodeArgument::OPTIONAL, 'Function, class, instance, constant, method or property to document.'),
            ])
            ->setDescription('Read the documentation for an object, class, constant, method or property.')
            ->setHelp(
                <<<HELP
Read the documentation for an object, class, constant, method or property.

It's awesome for well-documented code, not quite as awesome for poorly documented code.

e.g.
<return>>>> doc preg_replace</return>
<return>>>> doc Psy\Shell</return>
<return>>>> doc Psy\Shell::debug</return>
<return>>>> \$s = new Psy\Shell</return>
<return>>>> doc \$s->run</return>
<return>>>> doc --update-manual</return>
<return>>>> doc --update-manual=fr</return>
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
        if ($input->getOption('update-manual') !== false) {
            return $this->handleUpdateManual($input, $output);
        }

        $value = $input->getArgument('target');
        if (!$value) {
            throw new RuntimeException('Not enough arguments (missing: "target").');
        }

        if (ReflectionLanguageConstruct::isLanguageConstruct($value)) {
            $reflector = new ReflectionLanguageConstruct($value);
            $doc = $this->getManualDocById($value);
        } else {
            list($target, $reflector) = $this->getTargetAndReflector($value, $output);
            $doc = $this->getManualDoc($reflector) ?: DocblockFormatter::format($reflector);
        }

        $hasManual = $this->getShell()->getManual() !== null;

        if ($output instanceof ShellOutput) {
            $output->startPaging();
        }

        // Maybe include the declaring class
        if ($reflector instanceof \ReflectionMethod || $reflector instanceof \ReflectionProperty) {
            $output->writeln(SignatureFormatter::format($reflector->getDeclaringClass()));
        }

        $output->writeln(SignatureFormatter::format($reflector));
        $output->writeln('');

        if (empty($doc) && !$hasManual) {
            $output->writeln('<warning>PHP manual not found</warning>');
            $output->writeln('    To document core PHP functionality, download the PHP reference manual:');
            $output->writeln('    https://github.com/bobthecow/psysh/wiki/PHP-manual');
        } else {
            $output->writeln($doc);
        }

        // Implicit --all if the original docblock has an {@inheritdoc} tag.
        if ($input->getOption('all') || ($doc && \stripos($doc, self::INHERIT_DOC_TAG) !== false)) {
            $parent = $reflector;
            foreach ($this->getParentReflectors($reflector) as $parent) {
                $output->writeln('');
                $output->writeln('---');
                $output->writeln('');

                // Maybe include the declaring class
                if ($parent instanceof \ReflectionMethod || $parent instanceof \ReflectionProperty) {
                    $output->writeln(SignatureFormatter::format($parent->getDeclaringClass()));
                }

                $output->writeln(SignatureFormatter::format($parent));
                $output->writeln('');

                if ($doc = $this->getManualDoc($parent) ?: DocblockFormatter::format($parent)) {
                    $output->writeln($doc);
                }
            }
        }

        if ($output instanceof ShellOutput) {
            $output->stopPaging();
        }

        // Set some magic local variables
        $this->setCommandScopeVariables($reflector);

        return 0;
    }

    /**
     * Handle the manual update operation.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    private function handleUpdateManual(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config) {
            $output->writeln('<error>Configuration not available for manual updates.</error>');

            return 1;
        }

        // Create a synthetic input with the update-manual option
        $definition = new InputDefinition([
            new InputOption('update-manual', null, InputOption::VALUE_OPTIONAL, '', false),
        ]);

        // Get the language value: if true (no value), use null to preserve current language
        $lang = $input->getOption('update-manual');
        $updateValue = ($lang === true) ? null : $lang;

        $updateInput = new ArrayInput(['--update-manual' => $updateValue], $definition);
        $updateInput->setInteractive($input->isInteractive());

        try {
            $manualUpdate = ManualUpdate::fromConfig($this->config, $updateInput, $output);
            $result = $manualUpdate->run($updateInput, $output);

            if ($result === 0) {
                $output->writeln('');
                $output->writeln('Restart PsySH to use the updated manual.');
            }

            return $result;
        } catch (\RuntimeException $e) {
            $output->writeln(\sprintf('<error>%s</error>', $e->getMessage()));

            return 1;
        }
    }

    private function getManualDoc($reflector)
    {
        switch (\get_class($reflector)) {
            case \ReflectionClass::class:
            case \ReflectionObject::class:
            case \ReflectionFunction::class:
                $id = $reflector->name;
                break;

            case \ReflectionMethod::class:
                $id = $reflector->class.'::'.$reflector->name;
                break;

            case \ReflectionProperty::class:
                $id = $reflector->class.'::$'.$reflector->name;
                break;

            case \ReflectionClassConstant::class:
                // @todo this is going to collide with ReflectionMethod ids
                // someday... start running the query by id + type if the DB
                // supports it.
                $id = $reflector->class.'::'.$reflector->name;
                break;

            case ReflectionConstant::class:
                $id = $reflector->name;
                break;

            default:
                return false;
        }

        return $this->getManualDocById($id);
    }

    /**
     * Get all all parent Reflectors for a given Reflector.
     *
     * For example, passing a Class, Object or TraitReflector will yield all
     * traits and parent classes. Passing a Method or PropertyReflector will
     * yield Reflectors for the same-named method or property on all traits and
     * parent classes.
     *
     * @return \Generator a whole bunch of \Reflector instances
     */
    private function getParentReflectors($reflector): \Generator
    {
        $seenClasses = [];

        switch (\get_class($reflector)) {
            case \ReflectionClass::class:
            case \ReflectionObject::class:
                foreach ($reflector->getTraits() as $trait) {
                    if (!\in_array($trait->getName(), $seenClasses)) {
                        $seenClasses[] = $trait->getName();
                        yield $trait;
                    }
                }

                foreach ($reflector->getInterfaces() as $interface) {
                    if (!\in_array($interface->getName(), $seenClasses)) {
                        $seenClasses[] = $interface->getName();
                        yield $interface;
                    }
                }

                while ($reflector = $reflector->getParentClass()) {
                    yield $reflector;

                    foreach ($reflector->getTraits() as $trait) {
                        if (!\in_array($trait->getName(), $seenClasses)) {
                            $seenClasses[] = $trait->getName();
                            yield $trait;
                        }
                    }

                    foreach ($reflector->getInterfaces() as $interface) {
                        if (!\in_array($interface->getName(), $seenClasses)) {
                            $seenClasses[] = $interface->getName();
                            yield $interface;
                        }
                    }
                }

                return;

            case \ReflectionMethod::class:
                foreach ($this->getParentReflectors($reflector->getDeclaringClass()) as $parent) {
                    if ($parent->hasMethod($reflector->getName())) {
                        $parentMethod = $parent->getMethod($reflector->getName());
                        if (!\in_array($parentMethod->getDeclaringClass()->getName(), $seenClasses)) {
                            $seenClasses[] = $parentMethod->getDeclaringClass()->getName();
                            yield $parentMethod;
                        }
                    }
                }

                return;

            case \ReflectionProperty::class:
                foreach ($this->getParentReflectors($reflector->getDeclaringClass()) as $parent) {
                    if ($parent->hasProperty($reflector->getName())) {
                        $parentProperty = $parent->getProperty($reflector->getName());
                        if (!\in_array($parentProperty->getDeclaringClass()->getName(), $seenClasses)) {
                            $seenClasses[] = $parentProperty->getDeclaringClass()->getName();
                            yield $parentProperty;
                        }
                    }
                }
                break;
        }
    }

    private function getManualDocById($id)
    {
        if ($manual = $this->getShell()->getManual()) {
            switch ($manual->getVersion()) {
                case 2:
                    // v2 manual docs are pre-formatted and should be rendered as-is
                    return $manual->get($id);

                case 3:
                    if ($doc = $manual->get($id)) {
                        $width = $this->getTerminalWidth();
                        $formatter = new ManualFormatter($width, $manual);

                        return $formatter->format($doc);
                    }
                    break;
            }
        }

        return null;
    }

    /**
     * Get the current terminal width for text wrapping.
     *
     * @return int Terminal width in columns
     */
    private function getTerminalWidth(): int
    {
        // Query terminal size directly
        if (\function_exists('shell_exec')) {
            // Output format: "rows cols"
            $output = @\shell_exec('stty size </dev/tty 2>/dev/null');
            if ($output && \preg_match('/^\d+ (\d+)$/', \trim($output), $matches)) {
                return (int) $matches[1];
            }

            $width = @\shell_exec('tput cols </dev/tty 2>/dev/null');
            if ($width && \is_numeric(\trim($width))) {
                return (int) \trim($width);
            }
        }

        // Check COLUMNS environment variable (may be stale after resize)
        $width = \getenv('COLUMNS');
        if ($width && \is_numeric(\trim($width))) {
            return (int) \trim($width);
        }

        // Fallback to 100 if we can't detect
        return 100;
    }
}
