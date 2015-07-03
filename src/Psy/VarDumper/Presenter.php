<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\VarDumper;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * A Presenter service.
 */
class Presenter
{
    const VERBOSE = 1;

    private $cloner;
    private $dumper;
    private $exceptionsImportants = array(
        "\0*\0message",
        "\0*\0code",
        "\0*\0file",
        "\0*\0line",
        "\0Exception\0previous",
    );
    private $styles = array(
        'num'       => 'number',
        'const'     => 'const',
        'str'       => 'string',
        'cchr'      => 'default',
        'note'      => 'class',
        'ref'       => 'default',
        'public'    => 'public',
        'protected' => 'protected',
        'private'   => 'private',
        'meta'      => 'comment',
        'key'       => 'comment',
        'index'     => 'number',
    );

    public function __construct(OutputFormatter $formatter)
    {
        $this->dumper = new Dumper($formatter);
        $this->dumper->setStyles($this->styles);

        $this->cloner = new Cloner();
        $this->cloner->addCasters(array('*' => function ($obj, array $a, Stub $stub, $isNested, $filter = 0) {
            if ($filter || $isNested) {
                if ($obj instanceof \Exception) {
                    $a = Caster::filter($a, Caster::EXCLUDE_NOT_IMPORTANT | Caster::EXCLUDE_EMPTY, $this->exceptionsImportants);
                } else {
                    $a = Caster::filter($a, Caster::EXCLUDE_PROTECTED | Caster::EXCLUDE_PRIVATE);
                }
            }

            return $a;
        }));
    }

    /**
     * Register casters.
     *
     * @see http://symfony.com/doc/current/components/var_dumper/advanced.html#casters
     *
     * @param callable[] $casters A map of casters.
     */
    public function addCasters(array $casters)
    {
        $this->cloner->addCasters($casters);
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
        return $this->present($value, 0);
    }

    /**
     * Present a full representation of the value.
     *
     * If $depth is 0, the value will be presented as a ref instead.
     *
     * @param mixed $value
     * @param int   $depth   (default: null)
     * @param int   $options One of Presenter constants
     *
     * @return string
     */
    public function present($value, $depth = null, $options = 0)
    {
        $data = $this->cloner->cloneVar($value, !($options & self::VERBOSE) ? Caster::EXCLUDE_VERBOSE : 0);

        if (null !== $depth) {
            $data = $data->withMaxDepth($depth);
        }

        $output = '';
        $this->dumper->dump($data, function ($line, $depth) use (&$output) {
            if ($depth >= 0) {
                if ('' !== $output) {
                    $output .= PHP_EOL;
                }
                $output .= str_repeat('  ', $depth) . $line;
            }
        });

        return OutputFormatter::escape($output);
    }
}
