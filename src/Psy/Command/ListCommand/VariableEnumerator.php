<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command\ListCommand;

use Psy\Context;
use Psy\VarDumper\Presenter;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Variable Enumerator class.
 */
class VariableEnumerator extends Enumerator
{
    // n.b. this array is the order in which special variables will be listed
    private static $specialNames = array(
        '_', '_e', '__function', '__method', '__class', '__namespace', '__file', '__line', '__dir',
    );

    private $context;

    /**
     * Variable Enumerator constructor.
     *
     * Unlike most other enumerators, the Variable Enumerator needs access to
     * the current scope variables, so we need to pass it a Context instance.
     *
     * @param Presenter $presenter
     * @param Context   $context
     */
    public function __construct(Presenter $presenter, Context $context)
    {
        $this->context = $context;
        parent::__construct($presenter);
    }

    /**
     * {@inheritdoc}
     */
    protected function listItems(InputInterface $input, \Reflector $reflector = null, $target = null)
    {
        // only list variables when no Reflector is present.
        if ($reflector !== null || $target !== null) {
            return;
        }

        // only list variables if we are specifically asked
        if (!$input->getOption('vars')) {
            return;
        }

        $showAll   = $input->getOption('all');
        $variables = $this->prepareVariables($this->getVariables($showAll));

        if (empty($variables)) {
            return;
        }

        return array(
            'Variables' => $variables,
        );
    }

    /**
     * Get scope variables.
     *
     * @param bool $showAll Include special variables (e.g. $_)
     *
     * @return array
     */
    protected function getVariables($showAll)
    {
        $scopeVars = $this->context->getAll();
        uksort($scopeVars, function ($a, $b) {
            $aIndex = array_search($a, self::$specialNames);
            $bIndex = array_search($b, self::$specialNames);

            if ($aIndex !== false) {
                if ($bIndex !== false) {
                    return $aIndex - $bIndex;
                }

                return 1;
            }

            if ($bIndex !== false) {
                return -1;
            }

            // TODO: this should be natcasesort
            return strcasecmp($a, $b);
        });

        $ret = array();
        foreach ($scopeVars as $name => $val) {
            if (!$showAll && in_array($name, self::$specialNames)) {
                continue;
            }

            $ret[$name] = $val;
        }

        return $ret;
    }

    /**
     * Prepare formatted variable array.
     *
     * @param array $variables
     *
     * @return array
     */
    protected function prepareVariables(array $variables)
    {
        // My kingdom for a generator.
        $ret = array();
        foreach ($variables as $name => $val) {
            if ($this->showItem($name)) {
                $fname = '$' . $name;
                $ret[$fname] = array(
                    'name'  => $fname,
                    'style' => in_array($name, self::$specialNames) ? self::IS_PRIVATE : self::IS_PUBLIC,
                    'value' => $this->presentRef($val), // TODO: add types to variable signatures
                );
            }
        }

        return $ret;
    }
}
