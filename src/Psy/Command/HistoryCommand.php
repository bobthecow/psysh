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

use Psy\Output\ShellOutput;
use Psy\Readline\Readline;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Psy Shell history command.
 *
 * Shows, searches and replays readline history. Not too shabby.
 */
class HistoryCommand extends Command
{
    /**
     * Set the Shell's Readline service.
     *
     * @param Readline $readline
     */
    public function setReadline(Readline $readline)
    {
        $this->readline = $readline;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('history')
            ->setAliases(array('hist'))
            ->setDefinition(array(
                new InputOption('show',        's', InputOption::VALUE_REQUIRED, 'Show the given range of lines'),
                new InputOption('head',        'H', InputOption::VALUE_REQUIRED, 'Display the first N items.'),
                new InputOption('tail',        'T', InputOption::VALUE_REQUIRED, 'Display the last N items.'),

                new InputOption('grep',        'G', InputOption::VALUE_REQUIRED, 'Show lines matching the given pattern (string or regex).'),
                new InputOption('insensitive', 'i', InputOption::VALUE_NONE,     'Case insensitive search (requires --grep).'),
                new InputOption('invert',      'v', InputOption::VALUE_NONE,     'Inverted search (requires --grep).'),

                new InputOption('no-numbers',  'N', InputOption::VALUE_NONE,     'Omit line numbers.'),

                new InputOption('save',        '',  InputOption::VALUE_REQUIRED, 'Save history to a file.'),
                new InputOption('replay',      '',  InputOption::VALUE_NONE,     'Replay'),
                new InputOption('clear',       '',  InputOption::VALUE_NONE,     'Clear the history.'),
            ))
            ->setDescription('Show the Psy Shell history.')
            ->setHelp(
                <<<'HELP'
Show, search, save or replay the Psy Shell history.

e.g.
<return>>>> history --grep /[bB]acon/</return>
<return>>>> history --show 0..10 --replay</return>
<return>>>> history --clear</return>
<return>>>> history --tail 1000 --save somefile.txt</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateOnlyOne($input, array('show', 'head', 'tail'));
        $this->validateOnlyOne($input, array('save', 'replay', 'clear'));

        $history = $this->getHistorySlice(
            $input->getOption('show'),
            $input->getOption('head'),
            $input->getOption('tail')
        );
        $highlighted = false;

        $invert      = $input->getOption('invert');
        $insensitive = $input->getOption('insensitive');
        if ($pattern = $input->getOption('grep')) {
            if (substr($pattern, 0, 1) !== '/' || substr($pattern, -1) !== '/' || strlen($pattern) < 3) {
                $pattern = '/' . preg_quote($pattern, '/') . '/';
            }

            if ($insensitive) {
                $pattern .= 'i';
            }

            $this->validateRegex($pattern);

            $matches     = array();
            $highlighted = array();
            foreach ($history as $i => $line) {
                if (preg_match($pattern, $line, $matches) xor $invert) {
                    if (!$invert) {
                        $chunks = explode($matches[0], $history[$i]);
                        $chunks = array_map(array(__CLASS__, 'escape'), $chunks);
                        $glue   = sprintf('<urgent>%s</urgent>', self::escape($matches[0]));

                        $highlighted[$i] = implode($glue, $chunks);
                    }
                } else {
                    unset($history[$i]);
                }
            }
        } elseif ($invert) {
            throw new \InvalidArgumentException('Cannot use -v without --grep.');
        } elseif ($insensitive) {
            throw new \InvalidArgumentException('Cannot use -i without --grep.');
        }

        if ($save = $input->getOption('save')) {
            $output->writeln(sprintf('Saving history in %s...', $save));
            file_put_contents($save, implode(PHP_EOL, $history) . PHP_EOL);
            $output->writeln('<info>History saved.</info>');
        } elseif ($input->getOption('replay')) {
            if (!($input->getOption('show') || $input->getOption('head') || $input->getOption('tail'))) {
                throw new \InvalidArgumentException('You must limit history via --head, --tail or --show before replaying.');
            }

            $count = count($history);
            $output->writeln(sprintf('Replaying %d line%s of history', $count, ($count !== 1) ? 's' : ''));
            $this->getApplication()->addInput($history);
        } elseif ($input->getOption('clear')) {
            $this->clearHistory();
            $output->writeln('<info>History cleared.</info>');
        } else {
            $type = $input->getOption('no-numbers') ? 0 : ShellOutput::NUMBER_LINES;
            if (!$highlighted) {
                $type = $type | ShellOutput::OUTPUT_RAW;
            }

            $output->page($highlighted ?: $history, $type);
        }
    }

    /**
     * Extract a range from a string.
     *
     * @param string $range
     *
     * @return array [ start, end ]
     */
    private function extractRange($range)
    {
        if (preg_match('/^\d+$/', $range)) {
            return array($range, $range + 1);
        }

        $matches = array();
        if ($range !== '..' && preg_match('/^(\d*)\.\.(\d*)$/', $range, $matches)) {
            $start = $matches[1] ? intval($matches[1]) : 0;
            $end   = $matches[2] ? intval($matches[2]) + 1 : PHP_INT_MAX;

            return array($start, $end);
        }

        throw new \InvalidArgumentException('Unexpected range: ' . $range);
    }

    /**
     * Retrieve a slice of the readline history.
     *
     * @param string $show
     * @param string $head
     * @param string $tail
     *
     * @return array A slilce of history
     */
    private function getHistorySlice($show, $head, $tail)
    {
        $history = $this->readline->listHistory();

        if ($show) {
            list($start, $end) = $this->extractRange($show);
            $length = $end - $start;
        } elseif ($head) {
            if (!preg_match('/^\d+$/', $head)) {
                throw new \InvalidArgumentException('Please specify an integer argument for --head.');
            }

            $start  = 0;
            $length = intval($head);
        } elseif ($tail) {
            if (!preg_match('/^\d+$/', $tail)) {
                throw new \InvalidArgumentException('Please specify an integer argument for --tail.');
            }

            $start  = count($history) - $tail;
            $length = intval($tail) + 1;
        } else {
            return $history;
        }

        return array_slice($history, $start, $length, true);
    }

    /**
     * Validate that $pattern is a valid regular expression.
     *
     * @param string $pattern
     *
     * @return bool
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
     * Validate that only one of the given $options is set.
     *
     * @param InputInterface $input
     * @param array          $options
     */
    private function validateOnlyOne(InputInterface $input, array $options)
    {
        $count = 0;
        foreach ($options as $opt) {
            if ($input->getOption($opt)) {
                $count++;
            }
        }

        if ($count > 1) {
            throw new \InvalidArgumentException('Please specify only one of --' . implode(', --', $options));
        }
    }

    /**
     * Clear the readline history.
     */
    private function clearHistory()
    {
        $this->readline->clearHistory();
    }

    public static function escape($string)
    {
        return OutputFormatter::escape($string);
    }
}
