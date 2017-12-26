## Code style

Please make your code look like the other code in the project.

PsySH follows [PSR-1](http://php-fig.org/psr/psr-1/) and [PSR-2](http://php-fig.org/psr/psr-2/). The easiest way to do make sure you're following the coding standard is to [install `php-cs-fixer`](https://github.com/friendsofphp/php-cs-fixer) and run `php-cs-fixer fix` before committing.

## Branching model

Please branch off and send pull requests to the `develop` branch.

## Building the manual

```sh
svn co https://svn.php.net/repository/phpdoc/en/trunk/reference/ php_manual
bin/build_manual phpdoc_manual ~/.local/share/psysh/php_manual.sqlite
```

To build the manual for another language, switch out `en` above for `de`, `es`, or any of the other languages listed in the docs.

[Partial or outdated documentation is available for other languages](http://www.php.net/manual/help-translate.php) but these translations are outdated, so their content may be completely wrong or insecure!
