<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Presenter;

/**
 * A resource Presenter.
 */
class ResourcePresenter extends RecursivePresenter
{
    const FMT = '<resource>\\<%s <strong>resource #%s</strong>></resource>';

    /**
     * Resource presenter can present resources.
     *
     * @param mixed $value
     *
     * @return boolean
     */
    public function canPresent($value)
    {
        return is_resource($value);
    }

    /**
     * Present a reference to the value.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function presentRef($value)
    {
        $type = get_resource_type($value);
        if ($type === 'stream') {
            $meta = stream_get_meta_data($value);
            $type = sprintf('%s stream', $meta['stream_type']);
        }

        $id = str_replace('Resource id #', '', (string) $value);

        return sprintf(self::FMT, $type, $id);
    }

    /**
     * Present the resource.
     *
     * @param resource $value
     * @param int      $depth   (default: null)
     * @param int      $options One of Presenter constants
     *
     * @return string
     */
    public function presentValue($value, $depth = null, $options = 0)
    {
        if ($depth === 0 || !($options & Presenter::VERBOSE)) {
            return $this->presentRef($value);
        }

        return sprintf('%s %s', $this->presentRef($value), $this->formatMetadata($value));
    }

    /**
     * Format resource metadata.
     *
     * @param resource $value
     *
     * @return string
     */
    protected function formatMetadata($value)
    {
        $props = array();

        switch (get_resource_type($value)) {
            case 'stream':
                $props = stream_get_meta_data($value);
                break;

            case 'curl':
                $props = curl_getinfo($value);
                break;

            case 'xml':
                $props = array(
                    'current_byte_index'    => xml_get_current_byte_index($value),
                    'current_column_number' => xml_get_current_column_number($value),
                    'current_line_number'   => xml_get_current_line_number($value),
                    'error_code'            => xml_get_error_code($value),
                );
                break;
        }

        if (empty($props)) {
            return '{}';
        }

        $formatted = array();
        foreach ($props as $name => $value) {
            $formatted[] = sprintf('%s: %s', $name, $this->indentValue($this->presentSubValue($value)));
        }

        $template = sprintf('{%s%s%%s%s}', PHP_EOL, self::INDENT, PHP_EOL);
        $glue     = sprintf(',%s%s', PHP_EOL, self::INDENT);

        return sprintf($template, implode($glue, $formatted));
    }
}
