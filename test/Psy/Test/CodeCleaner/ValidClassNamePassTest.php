<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ValidClassNamePass;
use Psy\Exception\Exception;

class ValidClassNamePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new ValidClassNamePass());
    }

    /**
     * @dataProvider getInvalid
     */
    public function testProcessInvalid($code, $php54 = false)
    {
        try {
            $stmts = $this->parse($code);
            $this->traverse($stmts);
            $this->fail();
        } catch (Exception $e) {
            if ($php54 && version_compare(PHP_VERSION, '5.4', '<')) {
                $this->assertInstanceOf('Psy\Exception\ParseErrorException', $e);
            } else {
                $this->assertInstanceOf('Psy\Exception\FatalErrorException', $e);
            }
        }
    }

    public function getInvalid()
    {
        // class declarations
        return array(
            // core class
            array('class stdClass {}'),
            // capitalization
            array('class stdClass {}'),

            // collisions with interfaces and traits
            array('interface stdClass {}'),
            array('trait stdClass {}', true),

            // collisions inside the same code snippet
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                trait Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            ', true),
            array('
                trait Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            ', true),
            array('
                trait Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                interface Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            ', true),
            array('
                interface Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                trait Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            ', true),
            array('
                interface Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                interface Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '),

            // namespaced collisions
            array('
                namespace Psy\\Test\\CodeCleaner {
                    class ValidClassNamePassTest {}
                }
            '),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Beta {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Beta {}
                }
            '),

            // extends and implements
            array('class ValidClassNamePassTest extends NotAClass {}'),
            array('class ValidClassNamePassTest extends ArrayAccess {}'),
            array('class ValidClassNamePassTest implements stdClass {}'),
            array('class ValidClassNamePassTest implements ArrayAccess, stdClass {}'),
            array('interface ValidClassNamePassTest extends stdClass {}'),
            array('interface ValidClassNamePassTest extends ArrayAccess, stdClass {}'),

            // class instantiations
            array('new Psy_Test_CodeCleaner_ValidClassNamePass_Gamma();'),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    new Psy_Test_CodeCleaner_ValidClassNamePass_Delta();
                }
            '),

            // class constant fetch
            array('Psy\\Test\\CodeCleaner\\ValidClassNamePass\\NotAClass::FOO'),

            // static call
            array('Psy\\Test\\CodeCleaner\\ValidClassNamePass\\NotAClass::foo()'),
            array('Psy\\Test\\CodeCleaner\\ValidClassNamePass\\NotAClass::$foo()'),
        );
    }

    /**
     * @dataProvider getValid
     */
    public function testProcessValid($code)
    {
        $stmts = $this->parse($code);
        $this->traverse($stmts);
    }

    public function getValid()
    {
        $valid = array(
            // class declarations
            array('class Psy_Test_CodeCleaner_ValidClassNamePass_Epsilon {}'),
            array('namespace Psy\Test\CodeCleaner\ValidClassNamePass; class Zeta {}'),
            array('
                namespace { class Psy_Test_CodeCleaner_ValidClassNamePass_Eta {}; }
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Psy_Test_CodeCleaner_ValidClassNamePass_Eta {}
                }
            '),
            array('namespace Psy\Test\CodeCleaner\ValidClassNamePass { class stdClass {} }'),

            // class instantiations
            array('new stdClass();'),
            array('new stdClass();'),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Theta {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    new Theta();
                }
            '),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Iota {}
                    new Iota();
                }
            '),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Kappa {}
                }
                namespace {
                    new \\Psy\\Test\\CodeCleaner\\ValidClassNamePass\\Kappa();
                }
            '),

            // Class constant fetch (ValidConstantPassTest validates the actual constant)
            array('class A {} A::FOO'),
            array('$a = new DateTime; $a::ATOM'),
            array('interface A { const B = 1; } A::B'),

            // static call
            array('DateTime::createFromFormat()'),
            array('DateTime::$someMethod()'),
            array('Psy\Test\CodeCleaner\Fixtures\ClassWithStatic::doStuff()'),
            array('Psy\Test\CodeCleaner\Fixtures\ClassWithCallStatic::doStuff()'),

            // Allow `self` and `static` as class names.
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new self();
                    }
                }
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new SELF();
                    }
                }
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new self;
                    }
                }
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new static();
                    }
                }
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new Static();
                    }
                }
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new static;
                    }
                }
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function foo() {
                        return parent::bar();
                    }
                }
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function foo() {
                        return self::bar();
                    }
                }
            '),
            array('
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function foo() {
                        return static::bar();
                    }
                }
            '),

            array('class A { static function b() { return new A; } }'),
            array('
                class A {
                    const B = 123;
                    function c() {
                        return A::B;
                    }
                }
            '),
            array('class A {} class B { function c() { return new A; } }'),

            // recursion
            array('class A { function a() { A::a(); } }'),
        );

        // Ugh. There's gotta be a better way to test for this.
        if (class_exists('PhpParser\ParserFactory')) {
            // PHP 7.0 anonymous classes, only supported by PHP Parser v2.x
            $valid[] = array('$obj = new class() {}');
        }

        if (version_compare(PHP_VERSION, '5.5', '>=')) {
            $valid[] = array('interface A {} A::class');
            $valid[] = array('interface A {} A::CLASS');
            $valid[] = array('class A {} A::class');
            $valid[] = array('class A {} A::CLASS');
            $valid[] = array('A::class');
            $valid[] = array('A::CLASS');
        }

        return $valid;
    }
}
