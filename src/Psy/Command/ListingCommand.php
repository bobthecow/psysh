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

use Psy\Command\ShellAwareCommand;
use Psy\Formatter\Signature\SignatureFormatter;
use Psy\Util\Mirror;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List available local variables, object properties, etc.
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
            if (strlen($pattern) < 3 || substr($pattern, 0, 1) != substr($pattern, -1)) {
                $pattern = '/'.preg_quote($pattern, '/').'/';
            }
            $pattern .= 'i';

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

    protected function outputLong(OutputInterface $output, array $things, array $signatures, $highlighted, $pad)
    {
        $output->page(function($output) use ($things, $signatures, $highlighted, $pad) {
            foreach ($things as $i => $thing) {
                $output->writeln(sprintf("%s%s  %s", $highlighted[$i], str_repeat(' ', $pad - strlen($thing)), $signatures[$i]));
            }
        });
    }

    protected function getSignatures(array $things)
    {
        return array_map(function($thing) {
            return SignatureFormatter::format(Mirror::get($thing));
        }, $things);
    }

    abstract protected function listThings(InputInterface $input);
}
