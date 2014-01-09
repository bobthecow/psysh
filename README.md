# PsySH

[![Build Status](https://travis-ci.org/bobthecow/psysh.png?branch=master)](https://travis-ci.org/bobthecow/psysh)

Check out the [Interactive Debugging in PHP talk from OSCON](https://presentate.com/bobthecow/talks/php-for-pirates) on Presentate.


## Installation

PsySH [is available via Composer](https://packagist.org/packages/psy/psysh), or you can use it directly from this repository:

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


## PsySH configuration

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

    // Sets the maximum number of entries the history can contain.
    // If set to zero, the history size is unlimited.
    'historySize' => 0,

    // If set to true, the history will not keep duplicate entries.
    // Newest entries override oldest.
    // This is the equivalent of the HISTCONTROL=erasedups setting in bash.
    'eraseDuplicates' => false,

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

## Downloading the manual

The PsySH `doc` command is great for documenting source code, but you'll need a little something extra for PHP core documentation. Download one of the following PHP Manual files and drop it in `~/.psysh/`:

 * **[English](http://psysh.org/manual/en/php_manual.sqlite)**
 * [Brazilian Portuguese](http://psysh.org/manual/pt_BR/php_manual.sqlite)
 * [Chinese (Simplified)](http://psysh.org/manual/zh/php_manual.sqlite)
 * [French](http://psysh.org/manual/fr/php_manual.sqlite)
 * [German](http://psysh.org/manual/de/php_manual.sqlite)
 * [Italian](http://psysh.org/manual/it/php_manual.sqlite)
 * [Japanese](http://psysh.org/manual/ja/php_manual.sqlite)
 * [Polish](http://psysh.org/manual/pl/php_manual.sqlite)
 * [Romanian](http://psysh.org/manual/ro/php_manual.sqlite)
 * [Russian](http://psysh.org/manual/ru/php_manual.sqlite)
 * [Persian](http://psysh.org/manual/fa/php_manual.sqlite)
 * [Spanish](http://psysh.org/manual/es/php_manual.sqlite)
 * [Turkish](http://psysh.org/manual/tr/php_manual.sqlite)
