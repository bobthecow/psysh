<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\TabCompletion\Matcher;

use Psy\Context;
use Psy\ContextAware;

/**
 * An abstract tab completion Matcher which implements ContextAware.
 *
 * The AutoCompleter service will inject a Context instance into all
 * ContextAware Matchers.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
abstract class AbstractContextAwareMatcher extends AbstractMatcher implements ContextAware
{
    /**
     * Context instance (for ContextAware interface).
     *
     * @var Context
     */
    protected $context;

    /**
     * ContextAware interface.
     *
     * @param Context $context
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Get a Context variable by name.
     *
     * @param string $var Variable name
     *
     * @return mixed
     */
    protected function getVariable($var)
    {
        return $this->context->get($var);
    }

    /**
     * Get all variables in the current Context.
     *
     * The '$' prefix for each variable name is not included by default.
     *
     * @param bool $dollarPrefix Whether to prefix '$' to each name.
     *
     * @return array
     */
    protected function getVariables($dollarPrefix = false)
    {
        $variables = $this->context->getAll();
        if (!$dollarPrefix) {
            return $variables;
        } else {
            // Add '$' prefix to each name.
            $newvars = [];
            foreach ($variables as $name => $value) {
                $newvars['$'.$name] = $value;
            }
            return $newvars;
        }
    }
}
