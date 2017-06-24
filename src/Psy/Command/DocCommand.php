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

use Psy\Formatter\DocblockFormatter;
use Psy\Formatter\SignatureFormatter;
use Psy\Output\ShellOutput;
use Psy\Reflection\ReflectionLanguageConstruct;
use Psy\Shell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Output\Output;

/**
 * Read the documentation for an object, class, constant, method or property.
 */
class DocCommand extends ReflectingCommand
{
    /**
     * We define these here since like PHP language constructs there isn't reflection that
     * we can use to manage magic constants.
     *
     * @var array
     */
    private static $magicConstants = array(
        '__LINE__',
        '__FILE__',
        '__DIR__',
        '__FUNCTION__',
        '__CLASS__',
        '__TRAIT__',
        '__METHOD__',
        '__NAMESPACE__',
        '__COMPILER_HALT_OFFSET__');

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('doc')
            ->setAliases(array('rtfm', 'man'))
            ->setDefinition(array(
                new InputArgument('value', InputArgument::REQUIRED, 'Function, class, instance, constant, method or property to document.'),
            ))
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
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $value = $input->getArgument('value');

        if (self::isLanguageConstant($value)) {
            $doc = $this->getConstantsDocById($value);

            /**
             * @var ShellOutput $output
             */
            $output->page(function (Output $output) use ($doc, $value) {
                if (empty($doc)) {
                    $output->writeln('<warning>PHP constants documentation not found</warning>');
                } else {
                    if (substr($value, 0, 2) === '__') {
                        $constValue = '{magic constant}';
                    } else {
                        $constValue = constant($value);
                    }

                    $output->writeLn("<keyword>const</keyword> <const>$value</const> = <info>$constValue</info>");
                    $output->writeln('');
                    $output->writeln($doc);
                }
            });
            return;
        }

        if (ReflectionLanguageConstruct::isLanguageConstruct($value)) {
            $reflector = new ReflectionLanguageConstruct($value);
            $doc = $this->getManualDocById($value);
        } else {
            list($target, $reflector) = $this->getTargetAndReflector($value);
            $doc = $this->getManualDoc($reflector) ?: DocblockFormatter::format($reflector);
        }

        $db = $this->getApplication()->getManualDb();

        $output->page(function ($output) use ($reflector, $doc, $db) {
            $output->writeln(SignatureFormatter::format($reflector));
            $output->writeln('');

            if (empty($doc) && !$db) {
                $output->writeln('<warning>PHP manual not found</warning>');
                $output->writeln('    To document core PHP functionality, download the PHP reference manual:');
                $output->writeln('    https://github.com/bobthecow/dotfiles/wiki/PHP-manual');
            } else {
                $output->writeln($doc);
            }
        });

        // Set some magic local variables
        $this->setCommandScopeVariables($reflector);
    }

    private function getManualDoc($reflector)
    {
        switch (get_class($reflector)) {
            case 'ReflectionFunction':
                $id = $reflector->name;
                break;

            case 'ReflectionMethod':
                $id = $reflector->class . '::' . $reflector->name;
                break;

            default:
                return false;
        }

        return $this->getManualDocById($id);
    }

    private function getManualDocById($id)
    {
        if ($db = $this->getApplication()->getManualDb()) {
            return $db
                ->query(sprintf('SELECT doc FROM php_manual WHERE id = %s', $db->quote($id)))
                ->fetchColumn(0);
        }
    }

    /**
     * Get the documentation text from the php_constants database given the id (constant's name)
     *
     * @param string $id
     * @return string | null
     */
    private function getConstantsDocById($id)
    {
        /** @var \PDO $db */
        if ($db = $this->getApplication()->getConstantsDb()) {
            if ($query = $db->query(
                sprintf('SELECT doc FROM php_constant WHERE id = %s', $db->quote(strtoupper($id))))
            ) {
                return $query->fetchColumn(0);
            }
        }
        return null;
    }

    /**
     * Check whether keyword is a (known) core language constant.
     *
     * @param string $keyword
     * @return bool
     */
    protected static function isLanguageConstant($keyword)
    {
        return in_array(strtoupper($keyword), self::$magicConstants, false) ||
            array_key_exists(strtoupper($keyword), get_defined_constants(true)['Core']);
    }
}
