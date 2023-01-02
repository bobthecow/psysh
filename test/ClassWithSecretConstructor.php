<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

class ClassWithSecretConstructor
{
    private $privateProp = 'private prop';

    private function __construct($arg = null)
    {
        if ($arg !== null) {
            $this->privateProp = 'private prop '.\json_encode($arg);
        }
    }
}
