<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

use Psy\Formatter\Signature\ClassSignatureFormatter;
use Psy\Formatter\Signature\ConstantSignatureFormatter;
use Psy\Formatter\Signature\FunctionSignatureFormatter;
use Psy\Formatter\Signature\MethodSignatureFormatter;
use Psy\Formatter\Signature\PropertySignatureFormatter;
use Psy\Reflection\ReflectionConstant;
use Psy\Util\Autographer;

class AutographerTest extends \PHPUnit_Framework_TestCase
{
    const IGNORE_THIS   = 1;
    private $ignoreThis = 1;

    public function testGet()
    {
        $signature = Autographer::get(new \ReflectionFunction('sort'));
        $this->assertTrue($signature instanceof FunctionSignatureFormatter);

        $signature = Autographer::get(new \ReflectionClass($this));
        $this->assertTrue($signature instanceof ClassSignatureFormatter);

        $signature = Autographer::get(new \ReflectionObject($this));
        $this->assertTrue($signature instanceof ClassSignatureFormatter);

        $signature = Autographer::get(new ReflectionConstant($this, 'IGNORE_THIS'));
        $this->assertTrue($signature instanceof ConstantSignatureFormatter);

        $signature = Autographer::get(new \ReflectionMethod($this, 'testGet'));
        $this->assertTrue($signature instanceof MethodSignatureFormatter);

        $signature = Autographer::get(new \ReflectionProperty($this, 'ignoreThis'));
        $this->assertTrue($signature instanceof PropertySignatureFormatter);
    }
}
