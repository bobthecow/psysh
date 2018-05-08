.DEFAULT_GOAL := help

PSYSH_SRC = bin src box.json.dist composer.json build/stub
VERSION = $(shell git describe --tag --always --dirty=-dev)

COMPOSER_OPTS = --no-interaction --no-progress --verbose
COMPOSER_REQUIRE_OPTS = $(COMPOSER_OPTS) --no-update
COMPOSER_UPDATE_OPTS = $(COMPOSER_OPTS) --prefer-stable --no-dev --classmap-authoritative --prefer-dist

.PHONY: help
help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/## *//'


##
## Commands
##---------------------------------------------------------------------------

clean:  ## Clean all created artifacts
.PHONY: clean
clean:
	rm -rf build/*
	rm -rf dist/*
	rm -rf vendor-bin/*/vendor/


build:  ## Compile PHARs
.PHONY: build
build: build/psysh/psysh build/psysh-compat/psysh build/psysh-php54/psysh build/psysh-php54-compat/psysh


dist:   ## Build tarballs for distribution
.PHONY: dist
dist: dist/psysh-$(VERSION).tar.gz dist/psysh-$(VERSION)-compat.tar.gz dist/psysh-$(VERSION)-php54.tar.gz dist/psysh-$(VERSION)-php54-compat.tar.gz


#
# Rules from files
#---------------------------------------------------------------------------

composer.lock: composer.json
	@echo The composer.lock file is not synchronized with the composer.json file

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install

vendor-bin/box/vendor: vendor/bamarni
	composer bin box install

build/stub: bin/build-stub bin/psysh LICENSE
	bin/build-stub

build/psysh: bin/psysh src composer.json composer.lock box.json.dist build/stub
	rm -rf build/psysh || true
	mkdir build/psysh
	cp -R $(PSYSH_SRC) build/psysh/
	composer config --working-dir build/psysh platform.php 7.0
	composer require --working-dir build/psysh $(COMPOSER_REQUIRE_OPTS) php:'>=7.0.0'
	composer update --working-dir build/psysh $(COMPOSER_UPDATE_OPTS)

build/psysh/psysh: vendor-bin/box/vendor build/psysh
	vendor/bin/box compile --working-dir build/psysh

build/psysh-compat: bin/psysh src composer.json composer.lock box.json.dist build/stub
	rm -rf build/psysh-compat || true
	mkdir build/psysh-compat
	cp -R $(PSYSH_SRC) build/psysh-compat/
	composer config --working-dir build/psysh-compat platform.php 7.0
	composer require --working-dir build/psysh-compat $(COMPOSER_REQUIRE_OPTS) php:'>=7.0.0'
	composer require --working-dir build/psysh-compat $(COMPOSER_REQUIRE_OPTS) symfony/polyfill-iconv symfony/polyfill-mbstring hoa/console
	composer update --working-dir build/psysh-compat $(COMPOSER_UPDATE_OPTS)

build/psysh-compat/psysh: vendor-bin/box/vendor build/psysh-compat
	vendor/bin/box compile --working-dir build/psysh-compat

build/psysh-php54: bin/psysh src composer.json composer.lock box.json.dist build/stub
	rm -rf build/psysh-php54 || true
	mkdir build/psysh-php54
	cp -R $(PSYSH_SRC) build/psysh-php54/
	composer config --working-dir build/psysh-php54 platform.php 5.4
	composer update --working-dir build/psysh-php54 $(COMPOSER_UPDATE_OPTS)

build/psysh-php54/psysh: vendor-bin/box/vendor build/psysh-php54
	vendor/bin/box compile --working-dir build/psysh-php54

build/psysh-php54-compat: bin/psysh src composer.json composer.lock box.json.dist build/stub
	rm -rf build/psysh-php54-compat || true
	mkdir build/psysh-php54-compat
	cp -R $(PSYSH_SRC) build/psysh-php54-compat/
	composer config --working-dir build/psysh-php54-compat platform.php 5.4
	composer require --working-dir build/psysh-php54-compat $(COMPOSER_REQUIRE_OPTS) symfony/polyfill-iconv symfony/polyfill-mbstring hoa/console:^2.15
	composer update --working-dir build/psysh-php54-compat $(COMPOSER_UPDATE_OPTS)

build/psysh-php54-compat/psysh: vendor-bin/box/vendor build/psysh-php54-compat
	vendor/bin/box compile --working-dir build/psysh-php54-compat


#
# Rules for dist packages
#---------------------------------------------------------------------------

dist/psysh-$(VERSION).tar.gz: build/psysh/psysh
	tar -czf dist/psysh-$(VERSION).tar.gz build/psysh/psysh

dist/psysh-$(VERSION)-compat.tar.gz: build/psysh-compat/psysh
	tar -czf dist/psysh-$(VERSION)-compat.tar.gz build/psysh-compat/psysh

dist/psysh-$(VERSION)-php54.tar.gz: build/psysh-php54/psysh
	tar -czf dist/psysh-$(VERSION)-php54.tar.gz build/psysh-php54/psysh

dist/psysh-$(VERSION)-php54-compat.tar.gz: build/psysh-php54-compat/psysh
	tar -czf dist/psysh-$(VERSION)-php54-compat.tar.gz build/psysh-php54-compat/psysh
