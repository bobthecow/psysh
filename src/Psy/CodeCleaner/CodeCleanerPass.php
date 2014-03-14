<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PHPParser_NodeVisitorAbstract as NodeVisitorAbstract;

/**
 * A CodeCleaner pass is a PHPParser Node Visitor.
 */
abstract class CodeCleanerPass extends NodeVisitorAbstract
{
    // Wheee!
}
