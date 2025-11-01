<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

use Psy\Manual\ManualInterface;

/**
 * Formats structured manual data for display at runtime.
 *
 * Takes structured data from the v3 manual format and formats it for display,
 * adapting to terminal width and converting semantic tags to console styles.
 */
class ManualFormatter
{
    // Maximum width for text wrapping, even on very wide terminals
    private const MAX_WIDTH = 120;

    private ManualWrapper $wrapper;
    private int $width;
    private ?ManualInterface $manual;

    /**
     * @param int                  $width  Terminal width for text wrapping
     * @param ManualInterface|null $manual Optional manual for generating hyperlinks
     */
    public function __construct(int $width = 100, ?ManualInterface $manual = null)
    {
        $this->wrapper = new ManualWrapper();
        // Cap width at MAX_WIDTH for readability on ultra-wide terminals
        $this->width = \min($width, self::MAX_WIDTH);
        $this->manual = $manual;
    }

    /**
     * Format structured manual data for display.
     *
     * @param array $data Structured manual data
     */
    public function format(array $data): string
    {
        $output = [];

        // Format based on type
        switch ($data['type'] ?? '') {
            case 'function':
                $output[] = $this->formatFunction($data);
                break;
            case 'class':
                $output[] = $this->formatClass($data);
                break;
            case 'constant':
                $output[] = $this->formatConstant($data);
                break;
            default:
                // Generic fallback
                if (!empty($data['description'])) {
                    $output[] = $this->formatDescription($data['description']);
                }
        }

        return \implode("\n\n", \array_filter($output))."\n";
    }

    /**
     * Format a function entry.
     *
     * @param array $data Function data
     */
    private function formatFunction(array $data): string
    {
        $output = [];

        if (!empty($data['description'])) {
            $output[] = $this->formatDescription($data['description']);
        }

        if (!empty($data['params'])) {
            $output[] = $this->formatParameters($data['params']);
        }

        if (!empty($data['return'])) {
            $output[] = $this->formatReturn($data['return']);
        }

        if (!empty($data['seeAlso'])) {
            $output[] = $this->formatSeeAlso($data['seeAlso']);
        }

        return \implode("\n\n", \array_filter($output));
    }

    /**
     * Format a class entry.
     *
     * @param array $data Class data
     */
    private function formatClass(array $data): string
    {
        $output = [];

        // Description
        if (!empty($data['description'])) {
            $output[] = $this->formatDescription($data['description']);
        }

        // See also
        if (!empty($data['seeAlso'])) {
            $output[] = $this->formatSeeAlso($data['seeAlso']);
        }

        return \implode("\n\n", \array_filter($output));
    }

    /**
     * Format a constant entry.
     *
     * @param array $data Constant data
     */
    private function formatConstant(array $data): string
    {
        $output = [];

        if (isset($data['value'])) {
            $output[] = '<strong>Value:</strong> '.$this->thunkTags($data['value']);
        }

        if (!empty($data['description'])) {
            $output[] = $this->formatDescription($data['description']);
        }

        if (!empty($data['seeAlso'])) {
            $output[] = $this->formatSeeAlso($data['seeAlso']);
        }

        return \implode("\n\n", \array_filter($output));
    }

    /**
     * Format a description section.
     *
     * @param string $description Description text with semantic tags
     *
     * @return string Formatted description
     */
    private function formatDescription(string $description): string
    {
        $output = ['<comment>Description:</comment>'];

        $text = $this->thunkTags($description);
        $wrapped = $this->wrapper->wrap($text, $this->width - 2);

        $output = \array_merge($output, $this->indentWrappedLines($wrapped, '  '));

        return \implode("\n", $output);
    }

    /**
     * Format parameters section.
     *
     * @param array $params Parameter list
     */
    private function formatParameters(array $params): string
    {
        // Decide layout based on terminal width
        // Use table layout for wide terminals (80+), stacked for narrow
        if ($this->width >= 80) {
            return $this->formatParametersTable($params);
        } else {
            return $this->formatParametersStacked($params);
        }
    }

    /**
     * Format parameters as a table (for wide terminals).
     *
     * @param array $params Parameter list
     */
    private function formatParametersTable(array $params): string
    {
        $output = ['<comment>Param:</comment>'];

        // Calculate column widths (matching old format)
        $typeWidth = \max(\array_map(function ($param) {
            return \mb_strlen($param['type'] ?? 'mixed');
        }, $params));

        $nameWidth = \max(\array_map(function ($param) {
            return \mb_strlen($param['name']);
        }, $params));

        // Build columns with padding OUTSIDE style tags
        $indent = \str_repeat(' ', $typeWidth + $nameWidth + 6);
        $wrapWidth = $this->width - \mb_strlen($indent);

        foreach ($params as $param) {
            $type = $param['type'] ?? 'mixed';
            $name = $param['name'];
            $desc = $this->thunkTags($param['description'] ?? '');

            // Wrap in style tags first, THEN pad to avoid long color blocks
            $typeFormatted = '<info>'.$type.'</info>'.\str_repeat(' ', $typeWidth - \mb_strlen($type));
            $nameFormatted = '<strong>'.$name.'</strong>'.\str_repeat(' ', $nameWidth - \mb_strlen($name));

            // Wrap description with proper indentation
            if (!empty($desc)) {
                $wrapped = $this->wrapper->wrap($desc, $wrapWidth);
                $firstLine = '  '.$typeFormatted.'  '.$nameFormatted.'  ';
                $output = \array_merge($output, $this->indentWrappedLines($wrapped, $indent, $firstLine));
            } else {
                $output[] = '  '.$typeFormatted.'  '.$nameFormatted;
            }
        }

        return \implode("\n", $output);
    }

    /**
     * Format parameters stacked (for narrow terminals).
     *
     * @param array $params Parameter list
     */
    private function formatParametersStacked(array $params): string
    {
        $output = ['<comment>Param:</comment>'];

        // Calculate type width for alignment
        $typeWidth = \max(\array_map(function ($param) {
            return \mb_strlen($param['type'] ?? 'mixed');
        }, $params));

        foreach ($params as $param) {
            $type = \str_pad($param['type'] ?? 'mixed', $typeWidth);
            $name = $param['name'];

            $output[] = \sprintf('  <info>%s</info>  <strong>%s</strong>', $type, $name);

            if (!empty($param['description'])) {
                $desc = $this->thunkTags($param['description']);
                $indent = \str_repeat(' ', $typeWidth + 4);
                $wrapped = $this->wrapper->wrap($desc, $this->width - \mb_strlen($indent));
                $output = \array_merge($output, $this->indentWrappedLines($wrapped, $indent));
            }
        }

        return \implode("\n", $output);
    }

    /**
     * Format return value section.
     *
     * @param array $return Return value data
     */
    private function formatReturn(array $return): string
    {
        $output = ['<comment>Return:</comment>'];

        $type = $return['type'] ?? 'unknown';
        $desc = $return['description'] ?? '';

        $indent = \str_repeat(' ', \mb_strlen($type) + 4);
        $wrapWidth = $this->width - \mb_strlen($indent);

        if (!empty($desc)) {
            $desc = $this->thunkTags($desc);
            $wrapped = $this->wrapper->wrap($desc, $wrapWidth);
            $firstLine = \sprintf('  <info>%s</info>  ', $type);
            $output = \array_merge($output, $this->indentWrappedLines($wrapped, $indent, $firstLine));
        } else {
            $output[] = \sprintf('  <info>%s</info>', $type);
        }

        return \implode("\n", $output);
    }

    /**
     * Format see also section.
     *
     * @param array $seeAlso List of related functions/classes
     */
    private function formatSeeAlso(array $seeAlso): string
    {
        if (empty($seeAlso)) {
            return '';
        }

        $output = ['<comment>See Also:</comment>'];

        // Format items with hyperlinks if manual is available
        $items = \array_map(function ($item) {
            return $this->formatSeeAlsoItem($item);
        }, $seeAlso);

        // Don't wrap - console tags need to stay intact
        // Just join with commas and indent
        $output[] = '  '.\implode(', ', $items);

        return \implode("\n", $output);
    }

    /**
     * Format a single see also item with hyperlink if available.
     *
     * @param string $item Function or class name (may contain XML tags)
     */
    private function formatSeeAlsoItem(string $item): string
    {
        // Strip XML tags to get the actual function/class name
        $cleanItem = \strip_tags($item);

        // Check if this item exists in the manual
        $href = null;
        if ($this->manual !== null && $this->manual->get($cleanItem) !== null) {
            $href = LinkFormatter::getPhpNetUrl($cleanItem);
        }

        // Add parentheses to functions (like php.net and old manual format)
        // Items with <function> tags are functions, otherwise classes/constants
        $displayText = $cleanItem;
        if (\strpos($item, '<function>') !== false) {
            $displayText .= '()';
        }

        if ($href !== null) {
            return LinkFormatter::styleWithHref('info', $displayText, $href);
        }

        // No hyperlink; apply semantic tag formatting, then add parens if function
        $formatted = $this->thunkTags($item);
        if (\strpos($item, '<function>') !== false && \strpos($formatted, '()') === false) {
            $formatted .= '()';
        }

        return $formatted;
    }

    /**
     * Indent wrapped text lines.
     *
     * Takes wrapped text and adds indentation to each line.
     * The first line can have a different prefix than subsequent lines.
     *
     * @param string      $wrapped     Wrapped text (may contain newlines)
     * @param string      $indent      Indentation for continuation lines
     * @param string|null $firstIndent Optional different indentation for first line (defaults to $indent)
     *
     * @return array Lines with indentation applied
     */
    private function indentWrappedLines(string $wrapped, string $indent, ?string $firstIndent = null): array
    {
        $firstIndent = $firstIndent ?? $indent;
        $lines = \explode("\n", $wrapped);
        $output = [];

        foreach ($lines as $i => $line) {
            $output[] = ($i === 0 ? $firstIndent : $indent).$line;
        }

        return $output;
    }

    /**
     * Convert semantic XML tags to Symfony Console format tags.
     *
     * @param string $text Text with semantic tags
     *
     * @return string Text with console format tags
     */
    private function thunkTags(string $text): string
    {
        // First, escape any < and > that aren't part of our semantic tags
        // Protect our semantic tags by replacing them with placeholders
        $tagMap = [];
        $tagIndex = 0;

        // Protect semantic tags
        $semanticTags = ['parameter', 'function', 'constant', 'classname', 'type', 'literal', 'class'];
        foreach ($semanticTags as $tag) {
            $text = \preg_replace_callback(
                "/<{$tag}>|<\/{$tag}>/",
                function ($matches) use (&$tagMap, &$tagIndex) {
                    $placeholder = "\x00TAG{$tagIndex}\x00";
                    $tagMap[$placeholder] = $matches[0];
                    $tagIndex++;

                    return $placeholder;
                },
                $text
            );
        }

        // Now escape any remaining < and > (these are content, not tags)
        $text = \str_replace(['<', '>'], ['\\<', '\\>'], $text);

        // Restore protected tags
        $text = \str_replace(\array_keys($tagMap), \array_values($tagMap), $text);

        // Handle parameters: add $ prefix and make bold
        $text = \preg_replace_callback(
            '/<parameter>([^<]+)<\/parameter>/',
            function ($matches) {
                $name = $matches[1];
                // Add $ if not already present
                if ($name[0] !== '$') {
                    $name = '$'.$name;
                }

                return '<strong>'.$name.'</strong>';
            },
            $text
        );

        // Handle functions: add () suffix and make bold
        $text = \preg_replace_callback(
            '/<function>([^<]+)<\/function>/',
            function ($matches) {
                $name = $matches[1];
                // Add () if not already present
                if (\substr($name, -2) !== '()') {
                    $name .= '()';
                }

                return '<strong>'.$name.'</strong>';
            },
            $text
        );

        // Map other semantic tags to corresponding formats
        $replacements = [
            '<constant>'   => '<info>',
            '</constant>'  => '</info>',
            '<classname>'  => '<class>',
            '</classname>' => '</class>',
            '<class>'      => '<class>',
            '</class>'     => '</class>',
            '<type>'       => '<info>',
            '</type>'      => '</info>',
            '<literal>'    => '<return>',
            '</literal>'   => '</return>',
        ];

        $text = \str_replace(\array_keys($replacements), \array_values($replacements), $text);

        return $text;
    }
}
