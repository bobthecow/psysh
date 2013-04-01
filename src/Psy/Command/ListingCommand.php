<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Command\ShellAwareCommand;
use Psy\Exception\ErrorException;
use Psy\Exception\RuntimeException;
use Psy\Formatter\SignatureFormatter;
use Psy\Util\Mirror;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract 'list' command.
 */
abstract class ListingCommand extends ShellAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $things      = $this->listThings($input);
        $highlighted = false;

        $namespace = $this->shell->getNamespace();
        if ($namespace && !$input->getOption('all')) {
            $namespace = str_replace('\\\\', '\\', trim($namespace, '\\')) . '\\';

            // filter by namespace
            foreach ($things as $i => $name) {
                if (substr($name, 0, strlen($namespace)) !== $namespace) {
                    unset($things[$i]);
                }
            }
        }

        $invert = $input->getOption('invert');
        if ($pattern = $input->getOption('grep')) {
            if (preg_match('/^([^\w\s\\\\]).*([^\w\s\\\\])([imsADUXu]*)$/', $pattern, $matches) && $matches[1] == $matches[2]) {
                if (strpos($matches[3], 'i') === false) {
                    $pattern .= 'i';
                }
            } else {
                $pattern = '/'.preg_quote($pattern, '/').'/i';
            }

            $this->validateRegex($pattern);

            $matches     = array();
            $highlighted = array();
            foreach ($things as $i => $name) {
                if (preg_match($pattern, $name, $matches) xor $invert) {
                    if (!$invert) {
                        $highlighted[$i] = str_replace($matches[0], sprintf('<strong>%s</strong>', $matches[0]), $things[$i]);
                    }
                } else {
                    unset($things[$i]);
                }
            }
        } elseif ($invert) {
            throw new \InvalidArgumentException('Cannot use -v without --grep.');
        }

        if ($input->hasOption('verbose') && $input->getOption('verbose')) {
            $pad = max(array_map(function($thing) {
                return strlen($thing);
            }, $things));
            $this->outputLong($output, $things, $this->getSignatures($things), $highlighted, $pad);
        } else {
            $output->page($highlighted ?: $things);
        }
    }

    /**
     * Output a list of signatures as 'long'.
     *
     * @param OutputInterface $output
     * @param array           $things
     * @param array           $signatures
     * @param array           $highlighted
     * @param string          $pad
     */
    protected function outputLong(OutputInterface $output, array $things, array $signatures, array $highlighted, $pad)
    {
        $output->page(function($output) use ($things, $signatures, $highlighted, $pad) {
            foreach ($things as $i => $thing) {
                $output->writeln(sprintf("%s%s  %s", $highlighted[$i], str_repeat(' ', $pad - strlen($thing)), $signatures[$i]));
            }
        });
    }

    /**
     * Get signatures for each thing.
     *
     * @param array $things
     *
     * @return array Array of formatted signatures.
     */
    protected function getSignatures(array $things)
    {
        return array_map(function($thing) {
            return SignatureFormatter::format(Mirror::get($thing));
        }, $things);
    }

    /**
     * Validate that $pattern is a valid regular expression.
     *
     * @param string $pattern
     *
     * @return boolean
     */
    private function validateRegex($pattern)
    {
        set_error_handler(array('Psy\Exception\ErrorException', 'throwException'));
        try {
            preg_match($pattern, '');
        } catch (ErrorException $e) {
            throw new RuntimeException(str_replace('preg_match(): ', 'Invalid regular expression: ', $e->getRawMessage()));
        }
        restore_error_handler();
    }

    /**
     * Abstract listThings function.
     *
     * @param InputInterface $input
     *
     * @return array Array of things.
     */
    abstract protected function listThings(InputInterface $input);
}
