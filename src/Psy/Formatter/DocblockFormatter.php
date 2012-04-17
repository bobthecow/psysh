<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

use Psy\Formatter\Formatter;
use Psy\Util\Docblock;

class DocblockFormatter implements Formatter
{
    private static $vectorParamTemplates = array(
        'type' => 'info',
        'var'  => 'strong',
    );

    public static function format(\Reflector $reflector)
    {
        $docblock = new Docblock($reflector);
        $chunks   = array();

        if (!empty($docblock->desc)) {
            $chunks[] = '<comment>Description:</comment>';
            $chunks[] = self::indent($docblock->desc, '  ');
            $chunks[] = '';
        }


        if (!empty($docblock->tags)) {
            foreach ($docblock::$vectors as $name => $vector) {
                if (isset($docblock->tags[$name])) {
                    $chunks[] = sprintf('<comment>%s:</comment>', self::inflect($name));
                    $chunks[] = self::formatVector($vector, $docblock->tags[$name]);
                    $chunks[] = '';
                }
            }

            $tags = self::formatTags(array_keys($docblock::$vectors), $docblock->tags);
            if (!empty($tags)) {
                $chunks[] = $tags;
                $chunks[] = '';
            }
        }

        return rtrim(implode("\n", $chunks));
    }

    private static function formatVector(array $vector, array $lines)
    {
        $template = array(' ');
        foreach ($vector as $type) {
            $max = 0;
            foreach ($lines as $line) {
                $chunk = $line[$type];
                $cur = empty($chunk) ? 0 : strlen($chunk) + 1;
                if ($cur > $max) {
                    $max = $cur;
                }
            }

            $template[] = self::getVectorParamTemplate($type, $max);
        }
        $template = implode(' ', $template);

        return implode("\n", array_map(function($line) use ($template) {
            return vsprintf($template, $line);
        }, $lines));
    }

    private static function formatTags(array $skip, array $tags)
    {
        $chunks = array();

        foreach ($tags as $name => $values) {
            if (in_array($name, $skip)) {
                continue;
            }

            foreach ($values as $value) {
                $chunks[] = sprintf('<comment>%s%s</comment> %s', self::inflect($name), empty($value) ? '' : ':', $value);
            }

            $chunks[] = '';
        }

        return implode("\n", $chunks);
    }

    private static function getVectorParamTemplate($type, $max)
    {
        if (!isset(self::$vectorParamTemplates[$type])) {
            return sprintf('%%-%ds', $max);
        }

        return sprintf('<%s>%%-%ds</%s>', self::$vectorParamTemplates[$type], $max, self::$vectorParamTemplates[$type]);
    }

    private static function indent($text, $indent = '  ')
    {
        return $indent . str_replace("\n", "\n".$indent, $text);
    }

    private static function inflect($text)
    {
        $words = trim(preg_replace('/[\s_-]+/', ' ', preg_replace('/([a-z])([A-Z])/', '$1 $2', $text)));

        return implode(' ', array_map('ucfirst', explode(' ', $words)));
    }
}
