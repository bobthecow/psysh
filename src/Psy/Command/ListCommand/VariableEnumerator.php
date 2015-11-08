<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
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
    private static $specialVars = array('_', '_e');
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
     * @param bool $showAll Include special variables (e.g. $_).
     *
     * @return array
     */
    protected function getVariables($showAll)
    {
        $scopeVars = $this->context->getAll();
        uksort($scopeVars, function ($a, $b) {
            if ($a === '_e') {
                return 1;
            } elseif ($b === '_e') {
                return -1;
            } elseif ($a === '_') {
                return 1;
            } elseif ($b === '_') {
                return -1;
            } else {
                // TODO: this should be natcasesort
                return strcasecmp($a, $b);
            }
        });

        $ret = array();
        foreach ($scopeVars as $name => $val) {
            if (!$showAll && in_array($name, self::$specialVars)) {
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
                    'style' => in_array($name, self::$specialVars) ? self::IS_PRIVATE : self::IS_PUBLIC,
                    'value' => $this->presentRef($val), // TODO: add types to variable signatures
                );
            }
        }

        return $ret;
    }
}
