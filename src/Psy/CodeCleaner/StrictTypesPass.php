<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\Declare_ as DeclareStmt;
use PhpParser\Node\Stmt\DeclareDeclare;
use Psy\Exception\FatalErrorException;

/**
 * Provide implicit strict types declarations for for subsequent execution.
 *
 * The strict types pass remembers the last strict types declaration:
 *
 *     declare(strict_types=1);
 *
 * ... which it then applies implicitly to all future evaluated code, until it
 * is replaced by a new declaration.
 */
class StrictTypesPass extends CodeCleanerPass
{
    private $strictTypes = false;

    /**
     * If this is a standalone strict types declaration, remember it for later.
     *
     * Otherwise, apply remembered strict types declaration to to the code until
     * a new declaration is encountered.
     *
     * @throws FatalErrorException if an invalid `strict_types` declaration is found
     *
     * @param array $nodes
     */
    public function beforeTraverse(array $nodes)
    {
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            return;
        }

        $prependStrictTypes = $this->strictTypes;

        foreach ($nodes as $key => $node) {
            if ($node instanceof DeclareStmt) {
                foreach ($node->declares as $declare) {
                    if ($declare->key === 'strict_types') {
                        $value = $declare->value;
                        if (!$value instanceof LNumber || ($value->value !== 0 && $value->value !== 1)) {
                            throw new FatalErrorException('strict_types declaration must have 0 or 1 as its value');
                        }

                        $this->strictTypes = $value->value === 1;
                    }
                }
            }
        }

        if ($prependStrictTypes) {
            $first = reset($nodes);
            if (!$first instanceof DeclareStmt) {
                $declare = new DeclareStmt(array(new DeclareDeclare('strict_types', new LNumber(1))));
                array_unshift($nodes, $declare);
            }
        }

        return $nodes;
    }
}
