#!/bin/bash

failed=0

fail() {
  failed=1

  echo Failed
  echo
  echo $1
  echo
}

test_version() {
  echo -n "  Version:      "

  output=$(build/$1/psysh --version 2>/dev/null)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  echo "Passed"
}

test_psy_info() {
  echo -n "  \\Psy\\info():  "

  output=$(echo "\\Psy\\info()" | build/$1/psysh 2>/dev/null)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  [[ "$output" = *"PsySH version"* ]] || fail "$output"
  [[ "$output" = *"PHP version"* ]] || fail "$output"
  [[ "$output" = *"OS"* ]] || fail "$output"
  [[ "$output" = *"default includes"* ]] || fail "$output"

  echo "Passed"
}

for build in psysh psysh-compat psysh-php55 psysh-php55-compat; do
  echo "Testing $build phar"

  test_version $build
  test_psy_info $build

  echo
done

if [ $failed == 1 ]; then
  exit 1
fi
