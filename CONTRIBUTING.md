## Building the manual

```sh
svn co https://svn.php.net/repository/phpdoc/en/trunk/reference/ php_manual
bin/build_manual phpdoc_manual ~/.psysh/php_manual.sqlite
```

To build the manual for another language, switch out `en` above for `de`, `es`, or any of the other languages listed in the README.

[Partial or outdated documentation is available for other languages](http://www.php.net/manual/help-translate.php) but these translations are outdated, so their content may be completely wrong or insecure!