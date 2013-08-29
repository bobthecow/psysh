---
layout: default
title:  PsySH
---
<a class="section-head" id="top"></a>

<div class="jumbotron">
  <div class="container">
    <h1>PsySH</h1>

    <p>PsySH is a runtime developer console and debugger for PHP. Unlike <a href="http://php.net/manual/en/features.commandline.interactive.php"><code>php -a</code></a>, PsySH is a <abbr title="Read-Eval-Print Loop">REPL</abbr>, does automatic semicolon insertion, and is really hard to crash. In addition to working as a REPL, PsySH can be used as a debugger, much like javascript's <code>debugger</code> statement, saving you from the pain of endless <code>var_dump()</code> and <code>die()</code> iterations.</p>

    <p>For an overview of the state of PHP debugging and why PsySH might be for you, see the slides from <a href="https://presentate.com/bobthecow/talks/php-for-pirates">Interactive Debugging in PHP</a> at OSCON 2013.</p>

    <p><a href="https://github.com/bobthecow/psysh" class="btn btn-primary btn-lg">Get PsySH</a></p>
  </div>
</div>

<a class="section-head" id="install"></a>

## Installation

PsySH [is available via Composer](https://packagist.org/packages/psy/psysh), or you can use it directly from [the GitHub repository](https://github.com/bobthecow/psysh):

```
git clone https://github.com/bobthecow/psysh.git
cd psysh
./bin/psysh
```

But by far the easiest way to use it is to download the precompiled phar:

```
wget psysh.org/psysh
chmod +x psysh
./psysh
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
    // is pretty awful without it. But you can explicitly disable it if you hate
    // yourself or something.
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

<a class="section-head" id="usage"></a>

## Usage

### PsySH as a REPL

PsySH functions as a <abbr title="Read-Eval-Print Loop">REPL</abbr> for PHP right out of the box! Once you've [downloaded the phar file](#install), running it directly (`./psysh`) will drop you into an interactive prompt, like so:

```
~/psysh/bin$ ./psysh
Psy Shell v0.1.0-dev (PHP 5.4.9-4ubuntu2.2 — cli) by Justin Hileman
>>>
```

From here, you can start entering PHP code and see the result interactively:

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

To use this functionality, drop the psysh phar file into your project, include it:

```php
<?php
require('psysh');
```

Then, drop this line into your script where you'd like to have a breakpoint:

```
\Psy\Shell::debug(get_defined_vars());
```

When your script reaches this point, execution will be suspended and you'll be dropped into a PsySH shell. Your program state is loaded and available for you to inspect and experiment with.
