<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Layout;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\Helper;

/**
 * Display-width string helpers for terminal rendering and navigation.
 *
 * Width modes:
 * - width: plain text width, no formatting/ANSI stripping.
 * - widthWithoutFormatting: parse formatter tags, then measure visible width.
 * - widthWithoutAnsi: strip ANSI/control sequences from rendered output.
 */
class DisplayString
{
    /**
     * ANSI/control-sequence regexes for rendered terminal output.
     */
    private const ANSI_CSI_RX = '/\x1B\[[0-9;?]*[ -\/]*[@-~]/';
    private const ANSI_OSC_RX = '/\x1B\][^\x07\x1B]*(?:\x07|\x1B\\\\)/';
    private const ANSI_SGR_RX = '/\x1B\[([0-9;]*)m/';
    private const OSC8_LINK_RX = '/\x1B\]8;[^;]*;([^\x07\x1B]*)(?:\x07|\x1B\\\\)(.*?)\x1B\]8;;(?:\x07|\x1B\\\\)/s';

    /**
     * Measure plain text width with no stripping.
     */
    /** @var bool|null */
    private static $hasHelperWidth;

    public static function width(string $text): int
    {
        // Helper::width() added in Symfony 5.3; fall back to strlen() for older versions.
        if (self::$hasHelperWidth ?? (self::$hasHelperWidth = \method_exists(Helper::class, 'width'))) {
            return Helper::width($text);
        }

        /* @phan-suppress-next-line PhanDeprecatedFunction BC fallback for Symfony < 5.3. */
        return Helper::strlen($text);
    }

    /**
     * Measure width after applying/removing formatter markup.
     *
     * Use this for strings that intentionally contain formatter tags like
     * "<info>...</info>" and should be measured by their rendered width.
     */
    public static function widthWithoutFormatting(string $text, OutputFormatterInterface $formatter): int
    {
        return self::width(Helper::removeDecoration($formatter, $text));
    }

    /**
     * Measure width from already-rendered terminal output.
     *
     * This strips ANSI/control sequences only; it does not parse formatter
     * markup like "<info>...</info>".
     */
    public static function widthWithoutAnsi(string $text): int
    {
        return self::width(self::stripAnsi($text));
    }

    /**
     * Resolve the code-point offset for a target display width.
     *
     * The returned offset is always on a grapheme boundary.
     */
    public static function offsetForWidth(string $text, int $targetWidth): int
    {
        if ($targetWidth <= 0 || $text === '') {
            return 0;
        }

        $offset = 0;
        $width = 0;
        foreach (self::graphemes($text) as $grapheme) {
            $graphemeWidth = self::width($grapheme);
            if ($width + $graphemeWidth > $targetWidth) {
                break;
            }

            $width += $graphemeWidth;
            $offset += \mb_strlen($grapheme);
        }

        return $offset;
    }

    /**
     * Truncate text to a maximum display width with optional ellipsis.
     */
    public static function truncate(string $text, int $maxWidth, bool $withEllipsis = false): string
    {
        if ($maxWidth <= 0 || $text === '') {
            return '';
        }

        if (self::width($text) <= $maxWidth) {
            return $text;
        }

        if (!$withEllipsis || $maxWidth <= 3) {
            return Helper::substr($text, 0, $maxWidth);
        }

        $targetWidth = $maxWidth - 3;
        $offset = self::offsetForWidth($text, $targetWidth);

        return \mb_substr($text, 0, $offset).'...';
    }

    /**
     * Iterate grapheme clusters in a string.
     *
     * @return string[]
     */
    private static function graphemes(string $text): array
    {
        if (\preg_match_all('/\X/u', $text, $matches) > 0) {
            return $matches[0];
        }

        // Fallback to individual code points when grapheme matching fails.
        $length = \mb_strlen($text);
        $chars = [];
        for ($i = 0; $i < $length; $i++) {
            $chars[] = \mb_substr($text, $i, 1);
        }

        return $chars;
    }

    /**
     * Remove terminal ANSI/control sequences from rendered text.
     */
    public static function stripAnsi(string $text): string
    {
        return \preg_replace([self::ANSI_CSI_RX, self::ANSI_OSC_RX], '', $text) ?? $text;
    }

    /**
     * Extract OSC 8 hyperlinks with their visible display-cell ranges.
     *
     * @return array<int, array{uri: string, label: string, start: int, end: int}>
     */
    public static function hyperlinks(string $text): array
    {
        if (!\preg_match_all(self::OSC8_LINK_RX, $text, $matches, \PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $links = [];
        foreach ($matches[0] as $i => $match) {
            $label = self::stripAnsi($matches[2][$i][0]);
            $start = self::widthWithoutAnsi(\substr($text, 0, $match[1]));

            $links[] = [
                'uri'   => $matches[1][$i][0],
                'label' => $label,
                'start' => $start,
                'end'   => $start + self::width($label),
            ];
        }

        return $links;
    }

    /**
     * Find the OSC 8 hyperlink at a zero-indexed display-cell offset.
     *
     * @return array{uri: string, label: string, start: int, end: int}|null
     */
    public static function hyperlinkAt(string $text, int $offset): ?array
    {
        foreach (self::hyperlinks($text) as $link) {
            if ($offset >= $link['start'] && $offset < $link['end']) {
                return $link;
            }
        }

        return null;
    }

    /**
     * Underline the OSC 8 hyperlink beginning at a display-cell offset.
     */
    public static function underlineHyperlink(string $text, int $start): string
    {
        if (!\preg_match_all(self::OSC8_LINK_RX, $text, $matches, \PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        foreach ($matches[0] as $i => $match) {
            $linkStart = self::widthWithoutAnsi(\substr($text, 0, $match[1]));
            if ($linkStart !== $start) {
                continue;
            }

            $label = $matches[2][$i][0];
            $labelOffset = $matches[2][$i][1];
            $activeUnderline = self::hasActiveUnderline(\substr($text, 0, $match[1]));
            $labelUnderline = false;
            $hoveredLabel = \preg_replace_callback(self::ANSI_SGR_RX, function ($match) use (&$labelUnderline) {
                $parameters = \explode(';', $match[1]);
                foreach (self::sgrStyleParameterIndexes($parameters) as $i) {
                    if ($parameters[$i] === '4') {
                        $parameters[$i] = '21';
                        $labelUnderline = true;
                    }
                }

                return "\033[".\implode(';', $parameters).'m';
            }, $label) ?? $label;

            if ($activeUnderline) {
                $hoveredLabel = "\033[21m".$hoveredLabel;
            } elseif (!$labelUnderline) {
                $hoveredLabel = "\033[4m".$hoveredLabel;
            }

            return \substr($text, 0, $labelOffset)
                .$hoveredLabel."\033[24m"
                .\substr($text, $labelOffset + \strlen($label));
        }

        return $text;
    }

    private static function hasActiveUnderline(string $text): bool
    {
        if (!\preg_match_all(self::ANSI_SGR_RX, $text, $matches)) {
            return false;
        }

        $underlined = false;
        foreach ($matches[1] as $parameters) {
            $parameters = \explode(';', $parameters);
            foreach (self::sgrStyleParameterIndexes($parameters) as $i) {
                $parameter = $parameters[$i];
                if ($parameter === '' || $parameter === '0' || $parameter === '24') {
                    $underlined = false;
                } elseif ($parameter === '4' || $parameter === '21') {
                    $underlined = true;
                }
            }
        }

        return $underlined;
    }

    /**
     * Return SGR parameter indexes that represent styles rather than extended
     * color payloads.
     *
     * @param string[] $parameters
     *
     * @return int[]
     */
    private static function sgrStyleParameterIndexes(array $parameters): array
    {
        $indexes = [];
        for ($i = 0, $count = \count($parameters); $i < $count; $i++) {
            if (\in_array($parameters[$i], ['38', '48', '58'], true)) {
                if (($parameters[$i + 1] ?? null) === '5') {
                    $i += 2;

                    continue;
                }
                if (($parameters[$i + 1] ?? null) === '2') {
                    $i += 4;

                    continue;
                }
            }

            $indexes[] = $i;
        }

        return $indexes;
    }
}
