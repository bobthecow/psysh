## Code style

Please make your code look like the other code in the project.

PsySH follows [PSR-1](http://php-fig.org/psr/psr-1/) and [PSR-2](http://php-fig.org/psr/psr-2/). The easiest way to do make sure you're following the coding standard is to [install `php-cs-fixer`](https://github.com/friendsofphp/php-cs-fixer) and run `php-cs-fixer fix` before committing.

## Running tests

1. Run `make test` to run tests!

## Building pre-compiled phars

1. Run `make build` to build everything, or `make build/psysh/psysh` for just the default build.
2. Copy `build/psysh/psysh` somewhere useful like `/usr/local/bin`.
3. Profit!
