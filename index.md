---
layout: default
title:  PsySH
---
<a class="section-head" id="usage"></a>

## Usage

### PsySH as a REPL

PsySH functions as a <abbr title="Read-Eval-Print Loop">REPL</abbr> for PHP right out of the box! Once you've [installed PsySH](#install), running it directly (`psysh` or `./psysh`) will drop you into an interactive prompt, like so:

```
~ $ ./psysh
Psy Shell v0.1.0-dev (PHP 5.4.9-4ubuntu2.2 — cli) by Justin Hileman
>>>
```

From here, you can type PHP code and see the result interactively:

```
>>> function timesFive($x) {
...     $result = $x * 5;
...     return $result;
... }
=> null
>>> timesFive(10);
=> 50
>>>
```

### PsySH as a Debugger

To use PsySH as a debugger, install it as a Composer dependency or include the Phar directly in your project:

```php
<?php
require('/path/to/psysh');
```

Then, drop this line into your script where you'd like to have a breakpoint:

```php
\Psy\Shell::debug(get_defined_vars());
```

When your script reaches this point, execution will be suspended and you'll be dropped into a PsySH shell. Your program state is loaded and available for you to inspect and experiment with.

Pro Tip™: You don't have to use `get_defined_vars`… You can pass anything you want in as your debugging context:

```php
\Psy\Shell::debug(['app' => $myApp]);
```
<a class="section-head" id="configure"></a>

## Configuration

While PsySH strives to detect the right settings automatically, you might want to configure it yourself. Just add a file to `~/.psysh/rc.php`:

```php
<?php

return array(

    // In PHP 5.4+, PsySH will default to your `cli.pager` ini setting. If this
    // is not set, it falls back to `less`. It is recommended that you set up
    // `cli.pager` in your `php.ini` with your preferred output pager.
    //
    // If you are running PHP 5.3, or if you want to use a different pager only
    // for Psy shell sessions, you can override it here.
    'pager' => 'more',

    // By default, PsySH will use a 'forking' execution loop if pcntl is
    // installed. This is by far the best way to use it, but you can override
    // the default by explicitly enabling or disabling this functionality here.
    'usePcntl' => false,

    // PsySH uses readline if you have it installed, because interactive input
    // is pretty awful without it. But you can explicitly disable it if you
    // hate yourself or something.
    'useReadline' => false,

    // "Default includes" will be included once at the beginning of every PsySH
    // session. This is a good place to add autoloaders for your favorite
    // libraries.
    'defaultIncludes' => array(
        __DIR__.'/include/bootstrap.php',
    ),

    // While PsySH ships with a bunch of great commands, it's possible to add
    // your own for even more awesome. Any Psy command added here will be
    // available in your Psy shell sessions.
    'commands' => array(

        // The `parse` command is a command used in the development of PsySH.
        // Given a string of PHP code, it pretty-prints the
        // [PHP Parser](https://github.com/nikic/PHP-Parser) parse tree. It
        // prolly won't be super useful for most of you, but it's there if you
        // want to play :)
        new \Psy\Command\ParseCommand,
    ),
);
```