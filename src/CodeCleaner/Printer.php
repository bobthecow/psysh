<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node\Param;
use PhpParser\PrettyPrinter\Standard;

/**
 * @internal
 */
class Printer extends Standard
{
    protected function pParam(Param $node): string
    {
        $doc = $node->getAttribute('psysh.parameterDocComment');
        if ($doc !== null && !empty($node->hooks)) {
            $parameter = clone $node;
            $parameter->hooks = [];

            return parent::pParam($parameter).' '.$doc.' {'.$this->pStmts($node->hooks).$this->nl.'}';
        }

        $code = parent::pParam($node);

        return $doc === null ? $code : $code.' '.$doc;
    }
}
