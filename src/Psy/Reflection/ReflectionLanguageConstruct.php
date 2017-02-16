<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Reflection;

/**
 * A fake ReflectionFunction but for language constructs.
 */
class ReflectionLanguageConstruct extends \ReflectionFunctionAbstract
{
    public $keyword;

    /**
     * Language construct parameter definitions.
     */
    private static $languageConstructs = array(
        'isset' => array(
            'var' => array(),
            '...' => array(
                'isOptional'   => true,
                'defaultValue' => null,
            ),
        ),

        'unset' => array(
            'var' => array(),
            '...' => array(
                'isOptional'   => true,
                'defaultValue' => null,
            ),
        ),

        'empty' => array(
            'var' => array(),
        ),

        'echo' => array(
            'arg1' => array(),
            '...'  => array(
                'isOptional'   => true,
                'defaultValue' => null,
            ),
        ),

        'print' => array(
            'arg' => array(),
        ),

        'die' => array(
            'status' => array(
                'isOptional'   => true,
                'defaultValue' => 0,
            ),
        ),

        'exit' => array(
            'status' => array(
                'isOptional'   => true,
                'defaultValue' => 0,
            ),
        ),
    );

    /**
     * Construct a ReflectionLanguageConstruct object.
     *
     * @param string $name
     */
    public function __construct($keyword)
    {
        if (self::isLanguageConstruct($keyword)) {
            throw new \InvalidArgumentException('Unknown language construct: ' . $keyword);
        }

        $this->keyword = $keyword;
    }

    /**
     * This can't (and shouldn't) do anything :).
     *
     * @throws \RuntimeException
     */
    public static function export($name)
    {
        throw new \RuntimeException('Not yet implemented because it\'s unclear what I should do here :)');
    }

    /**
     * Get language construct name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->keyword;
    }

    /**
     * None of these return references.
     *
     * @return bool
     */
    public function returnsReference()
    {
        return false;
    }

    /**
     * Get language construct params.
     *
     * @return
     */
    public function getParameters()
    {
        $params = array();
        foreach (self::$languageConstructs[$this->keyword] as $parameter => $opts) {
            array_push($params, new ReflectionLanguageConstructParameter($this->keyword, $parameter, $opts));
        }

        return $params;
    }

    /**
     * To string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * Check whether keyword is a (known) language construct.
     *
     * @param $keyword
     *
     * @return bool
     */
    public static function isLanguageConstruct($keyword)
    {
        return array_key_exists($keyword, self::$languageConstructs);
    }
}
