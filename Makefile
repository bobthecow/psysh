PSYSH_SRC = bin src box.json.dist composer.json build/stub scoper.inc.php
PSYSH_SRC_FILES = $(shell find src -type f -name "*.php")
VERSION = $(shell git describe --tag --always --dirty=-dev)

COMPOSER_OPTS = --no-interaction --no-progress --verbose
COMPOSER_REQUIRE_OPTS = $(COMPOSER_OPTS) --no-update
COMPOSER_UPDATE_OPTS = $(COMPOSER_OPTS) --prefer-stable --no-dev --classmap-authoritative --prefer-dist

ifneq ($(CI),)
	PHPUNIT_OPTS = --verbose --coverage-clover=coverage.xml
endif


# Commands

.PHONY: help build clean dist test phpstan
.DEFAULT_GOAL := help

help:
	@echo "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[33mTargets:\033[0m"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-7s\033[0m %s\n", $$1, $$2}'

build: ## Compile psysh PHAR
build: build/psysh/psysh

clean: ## Clean all created artifacts
	rm -rf build/*
	rm -rf dist/*
	rm -rf vendor-bin/*/vendor/

dist: ## Build tarballs for distribution
dist: dist/psysh-$(VERSION).tar.gz

test: ## Run unit tests
test: vendor/bin/phpunit
	$< $(PHPUNIT_OPTS)

phpstan: ## Run static analysis
phpstan: vendor/bin/phpstan vendor/bin/phpunit
	vendor/bin/phpstan --memory-limit=1G analyse


# All the composer stuffs

composer.lock: composer.json
	composer install
	touch $@

vendor/autoload.php: composer.lock
	composer install
	touch $@

vendor-bin/%/vendor/autoload.php: vendor/autoload.php vendor-bin/%/composer.json
	composer bin $* install
	touch $@

vendor/bin/box: vendor-bin/box/vendor/autoload.php
	composer bin box install
	ln -sf ../../vendor-bin/box/vendor/humbug/box/bin/box $@

vendor/bin/phpunit: vendor-bin/phpunit/vendor/autoload.php
	composer bin phpunit install --ignore-platform-reqs
	ln -sf ../../vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit $@

vendor/bin/phpstan: vendor-bin/phpstan/vendor/autoload.php
	composer bin phpstan install --ignore-platform-reqs
	ln -sf ../../vendor-bin/phpstan/vendor/phpstan/phpstan/phpstan $@


# Lots of PHARs

build/stub: bin/build-stub bin/psysh LICENSE
	bin/build-stub

build/psysh: $(PSYSH_SRC) $(PSYSH_SRC_FILES)
	rm -rf $@ || true
	mkdir $@
	cp -R $(PSYSH_SRC) $@/
	sed -i -e "/^ *const VERSION =/ s/'.*'/'$(VERSION)'/" $@/src/Shell.php
	composer config --working-dir $@ platform.php 7.4
	composer require --working-dir $@ $(COMPOSER_REQUIRE_OPTS) php:'>=7.4'
	composer require --working-dir $@ $(COMPOSER_REQUIRE_OPTS) symfony/polyfill-iconv symfony/polyfill-mbstring
	composer require --working-dir $@ $(COMPOSER_REQUIRE_OPTS) --dev roave/security-advisories:dev-latest
	composer update --working-dir $@ $(COMPOSER_UPDATE_OPTS)

build/%/psysh: vendor/bin/box build/%
	vendor/bin/box compile --no-parallel --working-dir $(dir $@)


# Dist packages

dist/psysh-$(VERSION).tar.gz: build/psysh/psysh
	@mkdir -p $(@D)
	tar -C $(dir $<) -czf $@ $(notdir $<)
