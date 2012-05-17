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

        \Psy\Shell::debug(get_defined_vars());

        echo "\nend\n";
    }
}

$foo = new Foo;
$foo->bar();
