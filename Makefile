PSYSH_SRC = bin src box.json.dist composer.json build/stub scoper.inc.php
PSYSH_SRC_FILES = $(shell find src -type f -name "*.php")
VERSION = $(shell git describe --tag --always --dirty=-dev)

COMPOSER_OPTS = --no-interaction --no-progress --verbose
COMPOSER_REQUIRE_OPTS = $(COMPOSER_OPTS) --no-update
COMPOSER_UPDATE_OPTS = $(COMPOSER_OPTS) --prefer-stable --no-dev --classmap-authoritative --prefer-dist

ifneq ($(CI),)
	PHPUNIT_OPTS = --verbose --coverage-clover=coverage.xml
endif

ifdef EXCLUDE_GROUP
	PHPUNIT_OPTS += --exclude-group $(EXCLUDE_GROUP)
endif


# Commands

.PHONY: help build clean dist test test-phar smoketest phpstan
.DEFAULT_GOAL := help

help:
	@echo "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[33mTargets:\033[0m"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-9s\033[0m %s\n", $$1, $$2}'

build: ## Compile psysh PHAR
build: build/psysh/psysh

clean: ## Clean all created artifacts
	rm -rf build/psysh build/stub
	rm -rf dist/*
	rm -rf vendor-bin/*/vendor/

dist: ## Build tarballs for distribution
dist: dist/psysh-$(VERSION).tar.gz

test: ## Run unit tests
test: vendor/bin/phpunit
	$< $(PHPUNIT_OPTS)

test-phar: ## Run unit tests with PHAR bootstrap
test-phar: build/psysh/psysh
	$(eval PHAR_TEST_DIR := $(shell mktemp -d))
	@echo "Setting up isolated test environment in $(PHAR_TEST_DIR)..."
	@cp -r test $(PHAR_TEST_DIR)/
	@cp $< $(PHAR_TEST_DIR)/
	@cd $(PHAR_TEST_DIR) && \
		COMPOSER_ROOT_VERSION=1.0.0 composer init --no-interaction --name=psy/test --autoload=test/ 2>&1 | grep -v "PSR-4" || true && \
		COMPOSER_ROOT_VERSION=1.0.0 composer require --no-interaction --no-progress "phpunit/phpunit:^9.6" 2>&1 | grep -v "locking\|Extracting" | head -3 || true && \
		vendor/bin/phpunit --bootstrap psysh --exclude-group isolation-fail test/
	@rm -rf $(PHAR_TEST_DIR)

smoketest: ## Run smoke tests on existing binaries
smoketest: build/psysh/psysh
	test/smoketest.sh

phpstan: ## Run static analysis
phpstan: vendor/bin/phpstan vendor/bin/phpunit
	vendor/bin/phpstan --memory-limit=1G analyse

phan: ## Run phan
phan: vendor/bin/phan
	vendor/bin/phan --allow-polyfill-parser

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

vendor/bin/phan: vendor/autoload.php
	composer bin phan install --ignore-platform-reqs
	ln -sf ../../vendor-bin/phan/vendor/phan/phan/phan $@
	touch $@


# Lots of PHARs

build/stub: bin/build-stub bin/psysh LICENSE
	bin/build-stub

build/psysh: $(PSYSH_SRC) $(PSYSH_SRC_FILES) build/composer.json build/composer.lock
	composer validate --check-lock --no-interaction --working-dir build
	rm -rf $@ || true
	mkdir $@
	cp -R $(PSYSH_SRC) $@/
	cp build/composer.json build/composer.lock $@/
	@# Fetch and include latest PHP manual
	@if bin/fetch-manual; then \
		echo "Including bundled manual..."; \
		cp php_manual.php $@/; \
		rm php_manual.php; \
	else \
		echo "Manual fetch failed, continuing without bundled manual"; \
	fi
	sed -i -e "/^ *const VERSION =/ s/'.*'/'$(VERSION)'/" $@/src/Shell.php
	composer install --working-dir $@ $(COMPOSER_INSTALL_OPTS)

build/%/psysh: vendor/bin/box build/%
	vendor/bin/box compile --no-parallel --working-dir $(dir $@)


# Dist packages

dist/psysh-$(VERSION).tar.gz: build/psysh/psysh
	@mkdir -p $(@D)
	tar -C $(dir $<) -czf $@ $(notdir $<)
