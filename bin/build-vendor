#!/usr/bin/env bash

cd "${BASH_SOURCE%/*}/.."

rm -rf build-vendor

COMPOSER_VENDOR_DIR=build-vendor composer install --ignore-platform-reqs --no-dev --no-progress --classmap-authoritative
