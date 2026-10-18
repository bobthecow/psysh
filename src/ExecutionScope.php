<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

/**
 * Owns cleanup for one closure, loop iteration, or evaluation.
 *
 * @internal
 */
class ExecutionScope
{
    public const BOUNDARY = 0;
    public const LOOP = 1;
    public const EVALUATION = 2;
    public const LEGACY = 3;

    public int $type;
    public ?ExecutionCleanupState $output = null;
    public bool $ownsSignalChars = false;
    /** @var \Closure[] Cleanup actions in acquisition order. */
    public array $cleanups = [];

    public function __construct(int $type)
    {
        $this->type = $type;
    }
}
