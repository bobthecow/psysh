<?php

require_once __DIR__.'/vendor/autoload.php';

class Foo
{
    public function bar()
    {
        echo "\nstart\n";

        $a = 'one';
        $b = new StdClass;
        $b->name = 'bee';

        extract(\Psy\Shell::debug(get_defined_vars()));

        var_dump($a);
        var_dump($b);

        echo "\nend\n";
    }
}

$foo = new Foo;
$foo->bar();
