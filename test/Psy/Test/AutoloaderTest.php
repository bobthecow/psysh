<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Autoloader;

class AutoloaderTest extends \PHPUnit\Framework\TestCase
{
    public function testRegister()
    {
        Autoloader::register();
        $this->assertTrue(spl_autoload_unregister(array('Psy\Autoloader', 'autoload')));
    }
}
