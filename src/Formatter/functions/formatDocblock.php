<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

use Psy\Util\Docblock;
use Psy\Util\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;

if (!\function_exists('Psy\\Formatter\\formatDocblock')) {
    /**
     * Format a docblock.
     *
     * @param Docblock $docblock
     *
     * @return string Formatted docblock
     */
    function formatDocblock(Docblock $docblock)
    {
        $chunks = [];

        if (!empty($docblock->desc)) {
            $chunks[] = '<comment>Description:</comment>';
            $chunks[] = '  ' . \str_replace("\n", "\n  ", OutputFormatter::escape($docblock->desc));
            $chunks[] = '';
        }

        if (!empty($docblock->tags)) {
            foreach ($docblock::$vectors as $name => $vector) {
                if (isset($docblock->tags[$name])) {
                    $lines = $docblock->tags[$name];

                    $template = [' '];
                    foreach ($vector as $type) {
                        $max = 0;
                        foreach ($lines as $line) {
                            $chunk = $line[$type];
                            $cur = empty($chunk) ? 0 : \strlen($chunk) + 1;
                            if ($cur > $max) {
                                $max = $cur;
                            }
                        }

                        $style = null;
                        switch ($type) {
                            case 'type':
                                $style = 'info';
                                break;

                            case 'var':
                                $style = 'strong';
                                break;
                        }

                        if ($style !== null) {
                            $template[] = \sprintf('<%s>%%-%ds</%s>', $style, $max, $style);
                        } else {
                            $template[] = \sprintf('%%-%ds', $max);
                        }
                    }
                    $template = \implode(' ', $template);

                    $chunks[] = \sprintf('<comment>%s:</comment>', Str::toSentenceCase($name));
                    $chunks[] = \implode("\n", \array_map(function ($line) use ($template) {
                        $escaped = \array_map(function ($l) {
                            if ($l === null) {
                                return '';
                            }

                            return OutputFormatter::escape($l);
                        }, $line);

                        return \rtrim(\vsprintf($template, $escaped));
                    }, $lines));
                    $chunks[] = '';
                }
            }

            $vectorTagNames = \array_keys($docblock::$vectors);

            $tags = [];
            foreach ($docblock->tags as $name => $values) {
                if (\in_array($name, $vectorTagNames)) {
                    continue;
                }

                foreach ($values as $value) {
                    $tags[] = \sprintf('<comment>%s%s</comment> %s', Str::toSentenceCase($name), empty($value) ? '' : ':', OutputFormatter::escape($value));
                }

                $tags[] = '';
            }

            if (!empty($tags)) {
                $chunks[] = \implode("\n", $tags);
                $chunks[] = '';
            }
        }

        return \rtrim(\implode("\n", $chunks));
    }
}
