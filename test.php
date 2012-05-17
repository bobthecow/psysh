<?php

require_once __DIR__.'/vendor/autoload.php';

class Foo {
    public function bar() {
        echo "\nstart\n";

        $a = 'one';
        $b = new StdClass;
        $b->name = 'bee';

        extract(\Psy\debugger(get_defined_vars()));

        echo "b = ".json_encode($b), "\n";

        if (isset($c)) {
            echo "c is ".var_export($c, true);
        }

        echo "\nend\n";
    }
}

$foo = new Foo;
$foo->bar();
