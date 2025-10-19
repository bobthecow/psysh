#!/bin/bash

# This file is part of Psy Shell.
#
# (c) 2012-2025 Justin Hileman
#
# For the full copyright and license information, please view the LICENSE
# file that was distributed with this source code.

#
# PsySH Smoke Tests
#
# Integration smoke tests for PsySH binaries and PHARs. These tests verify
# that PsySH can start up, execute basic commands, handle CLI options, and
# exit cleanly without crashing.
#
# Usage:
#   test/smoketest.sh                    # Test both bin/psysh and build/psysh/psysh if they exist
#   test/smoketest.sh bin/psysh          # Test only the development binary
#   test/smoketest.sh build/psysh/psysh  # Test only the PHAR
#   test/smoketest.sh path/to/psysh.phar # Test a specific PHAR
#
# The script automatically detects whether it's testing a PHAR or development
# binary and adjusts the working directory accordingly to avoid local version
# detection issues.
#
# Tests performed:
#   - Version output
#   - Help output (including --warm-autoload flag)
#   - \Psy\info() function
#   - Built-in help command
#   - Basic REPL execution
#   - Math expressions
#   - CLI options (--quiet, --warm-autoload)
#   - Error handling
#   - Clean exit
#

set -e

failed=0

# Create temporary directory for PHAR tests
temp_dir=$(mktemp -d)
trap 'rm -rf "$temp_dir"' EXIT

# Default test targets
if [ "$#" -gt 0 ]; then
  test_targets=("$@")
else
  test_targets=()
  # Auto-detect available targets
  if [ -f "bin/psysh" ]; then
    test_targets+=("bin/psysh")
  fi
  for phar_target in build/*/psysh; do
    if [ -f "$phar_target" ]; then
      test_targets+=("$phar_target")
    fi
  done
fi

if [ ${#test_targets[@]} -eq 0 ]; then
  echo "No test targets found. Make sure bin/psysh exists or run 'make build' for PHAR."
  exit 1
fi

fail() {
  failed=1
  echo "FAILED"
  echo
  echo "$1"
  echo
}

pass() {
  echo "PASSED"
}

get_test_dir() {
  local target="$1"
  # For PHARs, run from temp directory to avoid local version detection
  if [[ "$target" == build/* ]] || [[ "$target" == *.phar ]]; then
    echo "$temp_dir"
  else
    # For development binary, run from project root
    pwd
  fi
}

resolve_target_path() {
  local target="$1"
  if [[ "$target" = /* ]]; then
    echo "$target"
  else
    echo "$(pwd)/$target"
  fi
}

test_version() {
  local target="$1"
  echo -n "  Version:           "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  local output
  output=$(cd "$test_dir" && php "$resolved_target" --version 2>&1)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  [[ "$output" =~ "Psy Shell" && "$output" =~ "PHP" ]] || { fail "Invalid version output: $output"; return; }

  pass
}

test_help() {
  local target="$1"
  echo -n "  Help:              "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  local output
  output=$(cd "$test_dir" && php "$resolved_target" --help 2>&1)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  [[ "$output" =~ "Usage:" ]] || { fail "Missing 'Usage:' in help output"; return; }
  [[ "$output" =~ "--warm-autoload" ]] || { fail "Missing '--warm-autoload' option in help"; return; }

  pass
}

test_psy_info() {
  local target="$1"
  echo -n "  \\Psy\\info():       "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  local output
  output=$(cd "$test_dir" && echo "\\Psy\\info()" | php "$resolved_target" 2>&1)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  [[ "$output" =~ "PsySH version" ]] || { fail "Missing 'PsySH version' in info output"; return; }
  [[ "$output" =~ "PHP version" ]] || { fail "Missing 'PHP version' in info output"; return; }
  [[ "$output" =~ "OS" ]] || { fail "Missing 'OS' in info output"; return; }
  [[ "$output" =~ "default includes" ]] || { fail "Missing 'default includes' in info output"; return; }

  pass
}

test_help_command() {
  local target="$1"
  echo -n "  help command:      "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  local output
  output=$(cd "$test_dir" && echo "help" | php "$resolved_target" 2>&1 | cat)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  [[ "$output" =~ "help" ]] || { fail "Missing 'help' in help command output"; return; }
  [[ "$output" =~ "Show a list of commands" ]] || { fail "Missing help description"; return; }
  [[ "$output" =~ "wtf" ]] || { fail "Missing 'wtf' command in help"; return; }
  [[ "$output" =~ "Show the backtrace of the most recent exception" ]] || { fail "Missing wtf description"; return; }
  [[ "$output" =~ "exit" ]] || { fail "Missing 'exit' command in help"; return; }
  [[ "$output" =~ "End the current session and return to caller" ]] || { fail "Missing exit description"; return; }

  pass
}

test_basic_repl() {
  local target="$1"
  echo -n "  Basic REPL:        "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  local output
  output=$(cd "$test_dir" && echo 'echo "Hello, World!"; exit' | php "$resolved_target" --no-interactive 2>&1)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  [[ "$output" =~ "Hello, World!" ]] || { fail "REPL execution failed"; return; }

  pass
}

test_math_expression() {
  local target="$1"
  echo -n "  Math expression:   "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  local output
  output=$(cd "$test_dir" && echo 'echo 2 + 2; exit' | php "$resolved_target" --no-interactive 2>&1)
  if [ $? != 0 ]; then
    fail "$output"
    return
  fi

  [[ "$output" =~ "4" ]] || { fail "Math expression failed, expected '4'"; return; }

  pass
}

test_cli_options() {
  local target="$1"
  echo -n "  CLI options:       "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  # Test --quiet flag
  local output
  output=$(cd "$test_dir" && echo 'echo "test"; exit' | php "$resolved_target" --quiet --no-interactive 2>&1)
  if [ $? != 0 ]; then
    fail "Quiet flag test failed: $output"
    return
  fi

  # Test --warm-autoload flag (should not error)
  output=$(cd "$test_dir" && echo 'echo "autoload test"; exit' | php "$resolved_target" --warm-autoload --no-interactive 2>&1)
  if [ $? != 0 ]; then
    fail "Warm autoload flag test failed: $output"
    return
  fi

  [[ "$output" =~ "autoload test" ]] || { fail "Warm autoload flag prevented execution"; return; }

  pass
}

test_error_handling() {
  local target="$1"
  echo -n "  Error handling:    "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  local output
  local exit_code
  set +e  # Disable exit on error for this test
  output=$(cd "$test_dir" && echo 'invalid php syntax here; exit' | php "$resolved_target" --no-interactive 2>&1)
  exit_code=$?
  set -e  # Re-enable exit on error

  # Should handle the error gracefully and show a parse error
  [[ "$output" =~ "Parse error" ]] || { fail "Expected parse error message"; return; }

  # Should exit with non-zero code after error
  if [ $exit_code == 0 ]; then
    fail "Expected non-zero exit code after parse error, got 0"
    return
  fi

  pass
}

test_exit_cleanly() {
  local target="$1"
  echo -n "  Exit cleanly:      "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  local output
  output=$(cd "$test_dir" && echo 'exit' | php "$resolved_target" --no-interactive 2>&1)
  if [ $? != 0 ]; then
    fail "Exit test failed: $output"
    return
  fi

  # Should not contain error messages
  [[ "$output" =~ "error" || "$output" =~ "Error" ]] && { fail "Exit contained error messages"; return; }

  pass
}

test_exit_status() {
  local target="$1"
  echo -n "  Exit status:       "

  local test_dir
  test_dir=$(get_test_dir "$target")

  local resolved_target
  resolved_target=$(resolve_target_path "$target")

  # Test exit with integer status code
  local output
  local exit_code
  set +e  # Temporarily disable exit on error for this test
  output=$(cd "$test_dir" && echo 'exit(42)' | php "$resolved_target" --no-interactive 2>&1)
  exit_code=$?
  set -e  # Re-enable exit on error
  if [ $exit_code != 42 ]; then
    fail "Expected exit code 42, got $exit_code"
    return
  fi

  # Test exit with string message (should exit with code 0)
  # Note: In non-interactive mode, BreakException messages aren't displayed
  set +e
  output=$(cd "$test_dir" && echo 'exit("Custom message")' | php "$resolved_target" --no-interactive 2>&1)
  exit_code=$?
  set -e
  if [ $exit_code != 0 ]; then
    fail "Expected exit code 0 for string message, got $exit_code"
    return
  fi

  # Test default exit (should be code 0)
  set +e
  output=$(cd "$test_dir" && echo 'exit()' | php "$resolved_target" --no-interactive 2>&1)
  exit_code=$?
  set -e
  if [ $exit_code != 0 ]; then
    fail "Expected exit code 0 for default exit, got $exit_code"
    return
  fi

  pass
}

echo "PsySH Smoke Tests"
echo "================="
echo

for target in "${test_targets[@]}"; do
  if [[ "$target" == build/* ]]; then
    echo "Testing PHAR: $target"
  else
    echo "Testing binary: $target"
  fi

  # Check if the target exists (resolve relative paths first)
  resolved_target=$(resolve_target_path "$target")

  if [ ! -f "$resolved_target" ]; then
    echo "  ERROR: $target not found"
    failed=1
    continue
  fi

  test_version "$target"
  test_help "$target"
  test_psy_info "$target"
  test_help_command "$target"
  test_basic_repl "$target"
  test_math_expression "$target"
  test_cli_options "$target"
  test_error_handling "$target"
  test_exit_cleanly "$target"
  test_exit_status "$target"

  echo
done

if [ $failed == 1 ]; then
  echo "Some tests failed!"
  exit 1
else
  echo "All tests passed!"
  exit 0
fi
