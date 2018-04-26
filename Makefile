.DEFAULT_GOAL := help

PHPNOGC=php -d zend.enable_gc=0

.PHONY: help
help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'


##
## Commands
##---------------------------------------------------------------------------

clean: 	 ## Clean all created artifacts
.PHONY: clean
clean:
	git clean --exclude=.idea/ -ffdx


build: ## Compile the application into the PHAR
.PHONY: build
build: bin/psysh.phar


#
# Rules from files
#---------------------------------------------------------------------------

composer.lock: composer.json
	composer install

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install

vendor-bin/box/vendor: vendor/bamarni
	composer bin box install

bin/psysh.phar: bin/psysh src vendor box.json.dist vendor-bin/box/vendor
	vendor/bin/box compile
