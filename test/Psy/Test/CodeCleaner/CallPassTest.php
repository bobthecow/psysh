<?php

namespace Psy\Test\CodeCleaner;

use PHPParser_PrettyPrinter_Default as Printer;
use Psy\CodeCleaner\CallPass;
use Symfony\Component\Process\PhpProcess;

class CallPassTest extends CodeCleanerTestCase
{
    public $errorTypes = array(
        E_WARNING => 'PHP Warning:  ',
        E_NOTICE => 'PHP Notice:  ',
        E_USER_WARNING => 'PHP Warning:  ',
        E_USER_NOTICE => 'PHP Notice:  ',
        E_STRICT => 'PHP Strict standards:  ',
    );

    public function setUp()
    {
        $this->setPass(new CallPass);
    }

    /**
     * @dataProvider getDataProcessAs
     */
    public function testProcessAs($from, $to)
    {
        $this->assertProcessesAs($from, $to);
    }

    public function getDataProcessAs()
    {
        $function = '\Psy\CodeCleaner\CallPass::callFunction';
        $method = '\Psy\CodeCleaner\CallPass::callMethod';
        $static = '\Psy\CodeCleaner\CallPass::callStatic';
        $arguments = '__CLASS__, \Psy\CodeCleaner\CallPass::getCalledClass(\Psy\CodeCleaner\CallPass::suppressErrors(), get_called_class())';

        return array(
            array('$a()', "$function(\$a, array(), array(), $arguments);"),
            array('f()', "$function('f', array(), array(), $arguments);"),
            array('a\\b()', "$function('a\\\\b', array(), array(), $arguments);"),
            array('namespace A; b()', "namespace A;\n\n$function('A\\\\b', array(), array(), $arguments);"),
            array('namespace A; function b() {}; b()', "namespace A;\n\nfunction b()\n{\n    \n}\n$function('A\\\\b', array(), array(), $arguments);"),
            array('namespace A; B\b()', "namespace A;\n\n$function('A\\\\B\\\\b', array(), array(), $arguments);"),
            array('namespace A; function b() {}; \b()', "namespace A;\n\nfunction b()\n{\n    \n}\n$function('b', array(), array(), $arguments);"),
            array('namespace A; \b()', "namespace A;\n\n$function('b', array(), array(), $arguments);"),
            array('namespace A; $a()', "namespace A;\n\n$function(\$a, array(), array(), $arguments);"),

            array('$a->b()', "$method(\$a, 'b', array(), array(), $arguments);"),
            array('A::b()', "$static('A', 'b', array(), array(), $arguments);"),
            array('namespace A; Foo::bar();', "namespace A;\n\n$static('A\\\\Foo', 'bar', array(), array(), $arguments);"),
            array('namespace A; B\Foo::bar();', "namespace A;\n\n$static('A\\\\B\\\\Foo', 'bar', array(), array(), $arguments);"),
            array('namespace A; class Foo {} Foo::bar();', "namespace A;\n\nclass Foo\n{\n    \n}\n$static('A\\\\Foo', 'bar', array(), array(), $arguments);"),
            array('namespace A; class Foo {} \Foo::bar();', "namespace A;\n\nclass Foo\n{\n    \n}\n$static('Foo', 'bar', array(), array(), $arguments);"),
            array('class Foo { public function bar() { static::foobar(); }}', "class Foo\n{\n    public function bar()\n    {\n        $static('static', 'foobar', array(), array(), $arguments);\n    }\n}"),

            array('f("foo", 3, $b)', "$function('f', array('foo', 3, &\${\\Psy\\CodeCleaner\\CallPass::checkVariable('b', array_merge(get_defined_vars(), isset(\$this) ? array() : array()))}), array(false, false, true), $arguments);"),
            array('f("foo", g(3), $b = 4)', "$function('f', array('foo', $function('g', array(3), array(false), $arguments), \$b = 4), array(false, null, null), $arguments);"),
            array('f(new DateTime)', "$function('f', array(new DateTime()), array(true), $arguments);"),
        );
    }

    /**
     * @dataProvider getCalls
     */
    public function testProcessCalls($code)
    {
        $printer = new Printer();
        $stmts = $this->parse($code);
        $fixedCode = $printer->prettyPrint($this->traverse($stmts));

        $processFixed = new PhpProcess('<?php
error_reporting(E_ALL | E_STRICT);
require_once "vendor/autoload.php";
try {
    '.$fixedCode.';
} catch (\Exception $e) {
    $f = fopen("php://stderr", "w");
    fwrite($f, $e->getMessage());
    fclose($f);
}
', __DIR__.'/../../../..');

        $processFixed->run();
        preg_match_all('{(PHP[^:]+(?<!Stack trace):\s*.+)(, called)? in }', $processFixed->getErrorOutput(), $matches);
        $errors = $matches[1];

        $process = new PhpProcess('<?php error_reporting(E_ALL | E_STRICT);'.$code);
        $process->run();

        preg_match_all('{(PHP[^:]+(?<!Stack trace):\s*.+?)(, called)? in - }', $process->getErrorOutput(), $matches);

        $this->assertEquals($process->getOutput(), $processFixed->getOutput(), sprintf("Fixed code:\n%s\nReal errors:\n%s\nProcess errors:\n%s\n", $fixedCode, implode("\n", $matches[1]), implode("\n", $errors)));
        $this->assertEquals(str_ireplace(' Strict Standards:', ' Notice:', $matches[1]), $errors, 'Fixed code: '.$fixedCode);
        $this->assertEquals(0, $processFixed->getExitCode(), sprintf("Fixed code:\n%s\nErrors:\n%s\n", $fixedCode, implode("\n", $errors)));
    }

    public function getCalls()
    {
        return array(
            // Fatal function errors
            array('$f = array("a" => "foo", "b" => "bar"); $f();'),
            array('$f = null; $f();'),
            array('$f = array(new \DateTime, "foo"); $f();'),
            array('$f = array(new \DateTime, null); $f();'),
            array('$f = array("foo", "bar"); $f();'),
            array('$f = array(null, "bar"); $f();'),
            array('$f = array(null, false); $f();'),
            array('function f(&$a) {}; f("foo");'),
            array('$f = function (&$a) {}; $f("foo");'),
            array('sort(array(2, 1));'),
            array('class A { private function __invoke() { return "foo"; }};
                   class B extends A {} $a = new B; echo $a();'),
            array('class A { private function foo() { return "foo"; }};
                   class B extends A { public function bar() { return $this->foo(); }} $b = new B; echo $b->bar();'),

            // Fatal method errors
            array('$a->f();'),
            array('$a = "Foo"; $b = null; $a->$b();'),
            array('Foo::a();'),
            array('class A {} echo A::bar();'),
            array('class A { private function foo() { return true; } }
                   class B extends A{ public function bar() { return $this->foo(); } }
                   $o = new B; $o->bar();'
            ),
            array('class A { private static function foo() { return true; } }
                   class B extends A { public static function bar() { return static::foo(); } }
                   B::bar();'
            ),

            array('class A { private function bar() {} } class B extends A { public function foo() { return static::bar(); } } $b = new B; $b->foo();'),

            // Valid function calls
            array('function f() { return "a"; }; echo f();'),
            array('function f(&$a) { return ++$a; }; $a = 1; f($a); echo $a;'),
            array('function f(&$a) { return ++$a; }; $a = 1; $b = "a"; f($$b); echo $a;'),
            array('$a = array(2, 1); sort($a); print_r($a);'),
            array('sort($a = array(2, 1));'),
            array('var_dump(sort());'),
            array('class A { private function __invoke() { return "foo"; }}; $a = new A; echo $a();'),
            array('var_dump(array_merge(array(1), array(2), array(3), array(4), array(5)));'),
            array('class A { private function __invoke($a) { return "foo"; }}; $a = new A; echo $a();'),
            array('class A { private function foo() { return "foo"; } public function __invoke() { $a = array($this, "foo"); return $a(); }}; $a = new A; echo $a();'),
            array('class A { public function foo(&$a) { return $this->bar($a); } private function bar(&$a) { return ++$a; }}; $a = 5; $o = new A; echo $o->foo($a);'),

            // Valid method calls
            array('class A { protected function foo() { return true; } }
                   class B extends A { public function bar() { return $this->foo(); } }
                   $o = new B; $o->bar();'
            ),
            array('class A { private function foo() { return __CLASS__; } public function bar($o) { return $o->foo(); }} $o = new A; echo $o->bar(new A);'),
            array('class A { protected function foo() { return __CLASS__; } public function bar($o) { return $o->foo(); }} $o = new A; echo $o->bar(new A);'),
            array('class A { protected function foo() { return __CLASS__; } public function bar() { return $this->foo(); }} $o = new A; echo $o->bar();'),
            array('class A { public static function foo() { return __METHOD__; } } echo A::foo();'),
            array('class A { public static function foo() { return static::bar(); } }
                   class B extends A { protected static function bar() { return __CLASS__; } } echo B::foo();'),

            // this
            array('class A { public static function foo() { return $this->bar(); }} A::foo();'),
            array('class A { public function foo() { $f = function() { $name = "this"; var_dump($$name->bar()); }; $f(); } public function bar() { return "bar"; }} $a = new A; $a->foo();'),
            array('class A { public function foo() { return new ReflectionObject($this); } } $a = new A; $a->foo();'),

            // self, static, parent
            array('parent::foo();'),
            array('self::foo();'),
            array('static::foo();'),
            array('paRent::foo();'),
            array('Self::foo();'),
            array('STATIC::foo();'),
            array('class A { public function foo() { return paRent::foo(); }} $a = new A; $a->foo();'),
            array('class A { public function foo() { return SELF::bar(); } public function bar() {}} $a = new A; $a->foo();'),
            array('class A { public function foo() { return stAtic::bar(); } public function bar() {}} $a = new A; $a->foo();'),

            // magic methods
            array('class A { private function __CALL($name, $args) { var_dump($name, $args); } }
                   class B extends A { } $o = new B; echo $o->foo(5, "ads", true, null);'),
            array('class A { private static function __CallStatic($name, $args) { var_dump($name, $args); } }
                   class B extends A { } B::foo(5, "ads", true, null);'),
            array('class A { public function __isset($name) { var_dump($name); return true;}} $a = new A; var_dump(isset($a->foo));'),

            // Fatal errors for PHP < 5.4
            array('$f = array(new \DateTime, "getTimestamp"); $f();'),
            array('$f = array("DateTime", "getLastErrors"); $f();'),
            array('$f = array(new \DateTime, "getLastErrors"); $f();'),
            array('class A { protected function foo() { return 5; }  public function __invoke() { $f = function() { return $this->foo(); }; return $f();}}; $a = new A; echo $a();'),

            // undefined variable as argument
            array('$f = false; echo strtolower($$f);'),
            array('$f = true; echo strtolower($$f);'),
            array('$f = 0; echo strtolower($$f);'),
            //array('$f = tmpfile(); echo strtolower($$f);'), // different id of resource
            array('set_error_handler(function() {}); $f = new stdClass; echo strtolower($$f);'),
            array('class A { function __toString() { return "foo";}} $f = new A; echo strtolower($$f);'),

            // get_class, get_called_class and get_parent_class
            array('var_dump(get_class());'),
            array('var_dump(get_class(null));'),
            array('var_dump(get_class("foo"));'),
            array('$a = new RecursiveArrayIterator(array()); var_dump(get_class($a));'),

            array('var_dump(get_called_class());'),
            array('var_dump(get_called_class(null));'),
            array('var_dump(get_called_class("foo"));'),
            array('$a = new RecursiveArrayIterator(array()); var_dump(get_called_class($a));'),

            array('var_dump(get_parent_class());'),
            array('var_dump(get_parent_class(null));'),
            array('var_dump(get_parent_class("foo"));'),
            array('$a = new RecursiveArrayIterator(array()); var_dump(get_parent_class($a));'),

            array('function f() { return get_class(); } var_dump(f());'),
            array('function f() { return get_class(null); } var_dump(f());'),
            array('function f() { return get_called_class(); } var_dump(f());'),
            array('function f() { return get_called_class(null); } var_dump(f());'),
            array('function f() { return get_parent_class(); } var_dump(f());'),

            array('$foo = 5; var_dump(get_class($foo));'),
            array('function bar() { return new StdClass(); } var_dump(get_class(bar()));'),
            array('$foo = "foo"; var_dump(get_called_class($foo));'),
            array('function bar() { return new stdClass(); } var_dump(get_called_class(bar()));'),
            array('function foo($bar) { return get_class($bar); } var_dump(foo("a"));'),
            array('function foo($bar) { return get_called_class($bar); } var_dump(foo("a"));'),
            array('class Foo { function bar() { var_dump(get_class(), get_class(null)); } } $f = new Foo; $f->bar();'),
            array('class Foo { function bar() { var_dump(get_called_class(), get_called_class(null)); } } $f = new Foo; $f->bar();'),
            array('class Foo { function bar() { var_dump(get_parent_class(), get_parent_class(null)); } } $f = new Foo; $f->bar();'),

            array('class Foo { function bar() { var_dump(geT_class(), get_CLASS(null)); } } $f = new Foo; $f->bar();'),
            array('class Foo { function bar() { var_dump(gET_called_Class(), get_called_CLASS(null)); } } $f = new Foo; $f->bar();'),
            array('class Foo { function bar() { var_dump(get_Parent_class(), get_parent_CLASS(null)); } } $f = new Foo; $f->bar();'),

            array('trait A { function bar() { var_dump(get_class(), get_class(null), get_class(self), get_class($this)); } } class B { use A; } $f = new B; $f->bar();'),
            array('trait A { function bar() { var_dump(get_called_class(), get_called_class(null), get_called_class(self), get_called_class($this)); } } class B { use A; } $f = new B; $f->bar();'),
            array('trait A { function bar() { var_dump(get_parent_class(), get_parent_class(null), get_parent_class(self), get_parent_class($this)); } } class B { use A; } $f = new B; $f->bar();'),

            array('class A { static function foo() { $f = function() { var_dump(get_called_class()); }; $f(); } }
                   class B extends A {}; A::foo(); B::foo();'
            ),

            // traits
            array('trait T { public function foo() { var_dump(__CLASS__);}} class A { use T;} $a = new A; $a->foo();'),
            array('trait T { private function foo() { var_dump(__CLASS__);}} class A { use T;} $a = new A; $a->foo();'),
            array('trait T { public function foo() { var_dump(__CLASS__);}} class A { use T { foo as protected; } } $a = new A; $a->foo();'),
            array('trait T { private function foo() { var_dump(__CLASS__);}} class A { use T { foo as public; } } $a = new A; $a->foo();'),
            array('trait T { protected function foo() { var_dump(__CLASS__);}} class A { use T; public function foo() { return parent::foo(); } } $a = new A; $a->foo();'),
        );
    }
}
