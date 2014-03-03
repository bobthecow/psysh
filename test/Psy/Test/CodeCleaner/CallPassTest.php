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
        $arguments = '__CLASS__, @get_called_class()';

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

            array('f("foo", 3, $b)', "$function('f', array('foo', 3, &\$b), array(false, false, true), $arguments);"),
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
        $stmts = $this->parse($code); //'namespace '.__NAMESPACE__.';'.$code
        $fixedCode = $printer->prettyPrint($this->traverse($stmts));

        $errors = array();
        try {
            ob_start();

            $that = $this;
            set_error_handler(function($level, $message) use (&$errors, $that) {
                if (0 !== error_reporting()) {
                    $errors[] = (isset($that->errorTypes[$level]) ? $that->errorTypes[$level] : '').$message;
                }
            });
            eval($fixedCode);
            restore_error_handler();

            $output = ob_get_contents();
            ob_end_clean();
        } catch (\Exception $e) {
            restore_error_handler();
            $output = ob_get_contents();
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            preg_match('{(.+) in }', $e->getMessage(), $matches);
            $errors[] = $matches[1];
        }

        $process = new PhpProcess('<?php '.$printer->prettyPrint($stmts));
        $process->run();

        preg_match_all('{(PHP[^:]+(?<!Stack trace):\s*.+?)(, called)? in }', $process->getErrorOutput(), $matches);

        $this->assertEquals($process->getOutput(), $output, sprintf("Fixed code:\n%s\nReal errors:\n%s\nProcess errors:\n%s\n", $fixedCode, implode("\n", $matches[1]), implode("\n", $errors)));
        $this->assertEquals(str_ireplace(' Strict Standards:', ' Notice:', $matches[1]), $errors, 'Fixed code: '.$fixedCode);
    }

    public function getCalls()
    {
        $name = function() {
            return 'n_'.md5(mt_rand());
        };

        return array(
            // Fatal function errors
            array('$f = array("a" => "foo", "b" => "bar"); $f();'),
            array('$f = null; $f();'),
            array('$f = array(new \DateTime, "foo"); $f();'),
            array('$f = array(new \DateTime, null); $f();'),
            array('$f = array("foo", "bar"); $f();'),
            array('$f = array(null, "bar"); $f();'),
            array('$f = array(null, false); $f();'),
            array('function '.($f = $name()).'(&$a) {}; '.$f.'("foo");'),
            array('$f = function (&$a) {}; $f("foo");'),
            array('sort(array(2, 1));'),
            array('class '.($c1 = $name()).' { private function __invoke() { return "foo"; }};
                   class '.($c2 = $name()).' extends '.$c1.' {} $a = new '.$c2.'; echo $a();'),
            array('class '.($c1 = $name()).' { private function foo() { return "foo"; }};
                   class '.($c2 = $name()).' extends '.$c1.' { public function bar() { return $this->foo(); }} $b = new '.$c2.'; echo $b->bar();'),

            // Fatal method errors
            array('$a->f();'),
            array('$a = "Foo"; $b = null; $a->$b();'),
            array('Foo::a();'),
            array('class '.($c = $name()).' {} echo '.$c.'::bar()'),
            array('class '.($c1 = $name()).' { private function foo() { return true; } }
                   class '.($c2 = $name()).' extends '.$c1.'{ public function bar() { return $this->foo(); } }
                   $o = new '.$c2.'; $o->bar();'
            ),
            array('class '.($c1 = $name()).' { private static function foo() { return true; } }
                   class '.($c2 = $name()).' extends '.$c1.'{ public static function bar() { return static::foo(); } }
                   '.$c2.'::bar();'
            ),


            // Valid function calls
            array('function '.($f = $name()).'() { return "a"; }; echo '.$f.'();'),
            array('function '.($f = $name()).'(&$a) { return ++$a; }; $a = 1; '.$f.'($a); echo $a;'),
            array('$a = array(2, 1); sort($a); print_r($a);'),
            array('sort($a = array(2, 1));'),
            array('var_dump(sort());'),
            array('class '.($c = $name()).' { private function __invoke() { return "foo"; }}; $a = new '.$c.'; echo $a();'),
            array('var_dump(array_merge(array(1), array(2), array(3), array(4), array(5)));'),
            array('class '.($c = $name()).' { private function __invoke($a) { return "foo"; }}; $a = new '.$c.'; echo $a();'),
            array('class '.($c = $name()).' { private function foo() { return "foo"; } public function __invoke() { $a = array($this, "foo"); return $a(); }}; $a = new '.$c.'; echo $a();'),
            array('class '.($c = $name()).' { public function foo(&$a) { return $this->bar($a); } private function bar(&$a) { return ++$a; }}; $a = 5; $o = new '.$c.'; echo $o->foo($a);'),

            // Valid method calls
            array('class '.($c1 = $name()).' { protected function foo() { return true; } }
                   class '.($c2 = $name()).' extends '.$c1.'{ public function bar() { return $this->foo(); } }
                   $o = new '.$c2.'; $o->bar();'
            ),
            array('class '.($c = $name()).' { private function foo() { return __CLASS__; } public function bar($o) { return $o->foo(); }} $o = new '.$c.'; echo $o->bar(new '.$c.');'),
            array('class '.($c = $name()).' { protected function foo() { return __CLASS__; } public function bar($o) { return $o->foo(); }} $o = new '.$c.'; echo $o->bar(new '.$c.');'),
            array('class '.($c = $name()).' { protected function foo() { return __CLASS__; } public function bar() { return $this->foo(); }} $o = new '.$c.'; echo $o->bar();'),
            array('class '.($c = $name()).' { public static function foo() { return __METHOD__; } } echo '.$c.'::foo();'),
            array('class '.($c1 = $name()).' { public static function foo() { return static::bar(); } }
                   class '.($c2 = $name()).' extends '.$c1.' { protected static function bar() { return __CLASS__; } } echo '.$c2.'::foo();'),

            array('class '.($c1 = $name()).' { private function __call($name, $args) { var_dump($name, $args); } }
                   class '.($c2 = $name()).' extends '.$c1.' { } $o = new '.$c2.'; echo $o->foo(5, "ads", true, null);'),

            array('class '.($c1 = $name()).' { private static function __callStatic($name, $args) { var_dump($name, $args); } }
                   class '.($c2 = $name()).' extends '.$c1.' { } '.$c2.'::foo(5, "ads", true, null);'),

            // Fatal errors for PHP < 5.4
            array('$f = array(new \DateTime, "getTimestamp"); $f();'),
            array('$f = array("DateTime", "getLastErrors"); $f();'),
            array('$f = array(new \DateTime, "getLastErrors"); $f();'),
            array('class '.($c = $name()).' { protected function foo() { return 5; }  public function __invoke() { $f = function() { return $this->foo(); }; return $f();}}; $a = new '.$c.'; echo $a();'),
        );
    }
}
