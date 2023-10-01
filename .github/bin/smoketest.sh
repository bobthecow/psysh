#!/bin/bash

failed=0

if [ "$#" -gt 0 ]; then
  build_versions=$@
else
  build_versions=(psysh)
fi

fail() {
  failed=1

  echo Failed
  echo
  echo $1
  echo
}

test_version() {
  echo -n "  Version:      "

  output=$(build/$1/psysh --version 2>&1)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  echo "Passed"
}

test_psy_info() {
  echo -n "  \\Psy\\info():  "

  output=$(echo "\\Psy\\info()" | build/$1/psysh 2>&1)
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

test_help_command() {
  echo -n "  help:         "

  output=$(echo "help" | build/$1/psysh 2>&1 | cat)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  [[ "$output" = *"help"* ]] || fail "$output"
  [[ "$output" = *"Show a list of commands."* ]] || fail "$output"
  [[ "$output" = *"wtf"* ]] || fail "$output"
  [[ "$output" = *"Show the backtrace of the most recent exception."* ]] || fail "$output"
  [[ "$output" = *"exit"* ]] || fail "$output"
  [[ "$output" = *"End the current session and return to caller."* ]] || fail "$output"

  echo "Passed"
}

for build in ${build_versions[@]}; do
  echo "Testing $build phar"

  test_version $build
  test_psy_info $build
  test_help_command $build

  echo
done

if [ $failed == 1 ]; then
  exit 1
fi
