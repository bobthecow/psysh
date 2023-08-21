<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ValidClassNamePass;

/**
 * @group isolation-fail
 */
class ValidClassNamePassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new ValidClassNamePass());
    }

    /**
     * @dataProvider getInvalid
     */
    public function testProcessInvalid($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function getInvalid()
    {
        // class declarations
        return [
            // core class
            ['class stdClass {}'],
            // capitalization
            ['class StdClass {}'],

            // collisions with interfaces and traits
            ['interface stdClass {}'],
            ['trait stdClass {}'],

            // collisions inside the same code snippet
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                trait Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '],
            ['
                trait Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '],
            ['
                trait Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                interface Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '],
            ['
                interface Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                trait Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '],
            ['
                interface Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
                interface Psy_Test_CodeCleaner_ValidClassNamePass_Alpha {}
            '],

            // namespaced collisions
            ['
                namespace Psy\\Test\\CodeCleaner {
                    class ValidClassNamePassTest {}
                }
            '],
            ['
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Beta {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Beta {}
                }
            '],

            // extends and implements
            ['class ValidClassNamePassTest extends NotAClass {}'],
            ['class ValidClassNamePassTest extends ArrayAccess {}'],
            ['class ValidClassNamePassTest implements stdClass {}'],
            ['class ValidClassNamePassTest implements ArrayAccess, stdClass {}'],
            ['interface ValidClassNamePassTest extends stdClass {}'],
            ['interface ValidClassNamePassTest extends ArrayAccess, stdClass {}'],
        ];
    }

    /**
     * @dataProvider getValid
     */
    public function testProcessValid($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function getValid()
    {
        return [
            // class declarations
            ['class Psy_Test_CodeCleaner_ValidClassNamePass_Epsilon {}'],
            ['namespace Psy\Test\CodeCleaner\ValidClassNamePass; class Zeta {}'],
            ['
                namespace { class Psy_Test_CodeCleaner_ValidClassNamePass_Eta {}; }
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Psy_Test_CodeCleaner_ValidClassNamePass_Eta {}
                }
            '],
            ['namespace Psy\Test\CodeCleaner\ValidClassNamePass { class stdClass {} }'],

            // class instantiations
            ['new stdClass();'],
            ['new stdClass();'],
            ['
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Theta {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    new Theta();
                }
            '],
            ['
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Iota {}
                    new Iota();
                }
            '],
            ['
                namespace Psy\\Test\\CodeCleaner\\ValidClassNamePass {
                    class Kappa {}
                }
                namespace {
                    new \\Psy\\Test\\CodeCleaner\\ValidClassNamePass\\Kappa();
                }
            '],

            // Class constant fetch
            ['class A {} A::FOO'],
            ['$a = new DateTime; $a::ATOM'],
            ['interface A { const B = 1; } A::B'],
            ['$foo = true ? A::class : B::class'],

            // static call
            ['DateTime::createFromFormat()'],
            ['DateTime::$someMethod()'],
            ['Psy\Test\CodeCleaner\Fixtures\ClassWithStatic::doStuff()'],
            ['Psy\Test\CodeCleaner\Fixtures\ClassWithCallStatic::doStuff()'],
            ['Psy\Test\CodeCleaner\Fixtures\TraitWithStatic::doStuff()'],

            // Allow `self` and `static` as class names.
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new self();
                    }
                }
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new SELF();
                    }
                }
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new self;
                    }
                }
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new static();
                    }
                }
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new Static();
                    }
                }
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function getInstance() {
                        return new static;
                    }
                }
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function foo() {
                        return parent::bar();
                    }
                }
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function foo() {
                        return self::bar();
                    }
                }
            '],
            ['
                class Psy_Test_CodeCleaner_ValidClassNamePass_ClassWithStatic {
                    public static function foo() {
                        return static::bar();
                    }
                }
            '],

            ['class A { static function b() { return new A; } }'],
            ['
                class A {
                    const B = 123;
                    function c() {
                        return A::B;
                    }
                }
            '],
            ['class A {} class B { function c() { return new A; } }'],

            // recursion
            ['class A { function a() { A::a(); } }'],

            // conditionally defined classes
            ['
                class A {}
                if (false) {
                    class A {}
                }
            '],
            ['
                class A {}
                if (true) {
                    class A {}
                } else if (false) {
                    class A {}
                } else {
                    class A {}
                }
            '],
            // ewww
            ['
                class A {}
                if (true):
                    class A {}
                elseif (false):
                    class A {}
                else:
                    class A {}
                endif;
            '],
            ['
                class A {}
                while (false) { class A {} }
            '],
            ['
                class A {}
                do { class A {} } while (false);
            '],
            ['
                class A {}
                switch (1) {
                    case 0:
                        class A {}
                        break;
                    case 1:
                        class A {}
                        break;
                    case 2:
                        class A {}
                        break;
                }
            '],
            ['
                interface A {} A::class
            '],
            ['
                interface A {} A::CLASS
            '],
            ['
                class A {} A::class
            '],
            ['
                A::class
            '],
            ['
                A::CLASS
            '],

            // PHP 7+ anonymous classes
            ['$obj = new class() {}'],
            ['new class() {}; new class() {}'],
        ];
    }
}
