#!/usr/bin/env bash

# This file is part of Psy Shell.
#
# (c) 2012-2026 Justin Hileman
#
# For the full copyright and license information, please view the LICENSE
# file that was distributed with this source code.

#
# PsySH PTY Smoke Tests
#
# Integration smoke tests that run PsySH under a PTY (pseudo-terminal) via
# the Linux util-linux `script` command. These tests verify interactive
# behavior that requires a real terminal: command execution, pager lifecycle,
# Composer proxy startup, and project trust flags.
#
# Usage:
#   test/smoketest-pty.sh                    # Test bin/psysh (default)
#   test/smoketest-pty.sh build/psysh/psysh  # Test a specific binary
#
# Requires Linux (for util-linux `script -qefc` support).
#

set -euo pipefail

if [[ "$(uname -s)" != "Linux" ]]; then
  echo "Skipping PTY smoke tests: Linux util-linux 'script' is required."
  exit 0
fi

if ! command -v script >/dev/null 2>&1; then
  echo "The 'script' command is required for PTY smoke tests."
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly ROOT_DIR
readonly TARGET="${1:-bin/psysh}"

if [[ ! -f "${ROOT_DIR}/${TARGET}" && ! -f "${TARGET}" ]]; then
  echo "PsySH target not found: ${TARGET}"
  exit 1
fi

FAILED=0
LAST_OUTPUT=''
LAST_STATUS=0
TMP_DIR="$(mktemp -d)"
readonly TMP_DIR
readonly HOME_DIR="${TMP_DIR}/home"
readonly CONFIG_DIR="${HOME_DIR}/.config"
readonly CONFIG_FILE="${TMP_DIR}/psysh.php"
readonly PROXY_PROJECT="${TMP_DIR}/proxy-project"
readonly PAGER_SCRIPT="${TMP_DIR}/pager.sh"
readonly TRUST_RUNNER="${TMP_DIR}/trust-runner.php"

trap 'rm -rf "${TMP_DIR}"' EXIT

mkdir -p "${CONFIG_DIR}/psysh" "${PROXY_PROJECT}/vendor/bin" "${PROXY_PROJECT}/vendor"
printf '<?php return [];%s' $'\n' > "${CONFIG_FILE}"
printf '<?php%s' $'\n' > "${PROXY_PROJECT}/vendor/autoload.php"
cat > "${PROXY_PROJECT}/composer.lock" <<'JSON'
{
  "packages": [
    {
      "name": "psy/psysh"
    }
  ],
  "packages-dev": []
}
JSON

cat > "${PAGER_SCRIPT}" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
cat
IFS= read -r -n 1 key < /dev/tty || exit 1
[[ "${key}" == "q" ]]
EOF

cat > "${TRUST_RUNNER}" <<'PHP'
<?php
require $argv[1];

$binPath = $argv[2];
$projectDir = $argv[3];
$trustMode = $argv[4];
$trustValue = $argv[5];

unset($_SERVER['PSYSH_UNTRUSTED_PROJECT'], $_SERVER['PSYSH_TRUST_PROJECT']);
unset($_ENV['PSYSH_UNTRUSTED_PROJECT'], $_ENV['PSYSH_TRUST_PROJECT']);
putenv('PSYSH_UNTRUSTED_PROJECT');
putenv('PSYSH_TRUST_PROJECT');

$_SERVER['argv'] = ['psysh'];
$_SERVER['argc'] = 1;

if ($trustMode === 'flag') {
    $_SERVER['argv'][] = $trustValue;
    $_SERVER['argc']++;
} elseif ($trustMode === 'env') {
    $_SERVER['PSYSH_TRUST_PROJECT'] = $trustValue;
    $_ENV['PSYSH_TRUST_PROJECT'] = $trustValue;
    putenv('PSYSH_TRUST_PROJECT='.$trustValue);
}

chdir($projectDir);

ob_start();
include $binPath;
ob_end_clean();

echo json_encode([
    'trust' => getenv('PSYSH_TRUST_PROJECT'),
    'untrusted' => getenv('PSYSH_UNTRUSTED_PROJECT'),
]);
PHP

BIN_PATH="$(cd "${ROOT_DIR}" && php -r 'echo realpath($argv[1]);' "${TARGET}")"
readonly BIN_PATH

cat > "${PROXY_PROJECT}/vendor/bin/psysh" <<PHP
#!/usr/bin/env php
<?php
\$GLOBALS['_composer_autoload_path'] = __DIR__ . '/../autoload.php';
require ${BIN_PATH@Q};
PHP

chmod +x "${PAGER_SCRIPT}" "${PROXY_PROJECT}/vendor/bin/psysh"

normalize_output() {
  perl -pe 's/\e\[[0-9;?]*[ -\/]*[@-~]//g; s/\r//g; s/.\x08//g;'
}

run_pty() {
  local input="$1"
  shift

  local command
  printf -v command '%q ' "$@"
  command="${command% }"

  set +e
  LAST_OUTPUT="$(printf '%b' "${input}" | script -qefc "${command}" /dev/null 2>&1 | normalize_output)"
  LAST_STATUS=$?
  set -e
}

run_php() {
  set +e
  LAST_OUTPUT="$("$@" 2>&1 | normalize_output)"
  LAST_STATUS=$?
  set -e
}

fail() {
  FAILED=1
  echo "FAILED"
  echo
  echo "${1}"
  echo
}

pass() {
  echo "PASSED"
}

assert_status() {
  local expected="$1"
  if [[ "${LAST_STATUS}" -ne "${expected}" ]]; then
    fail "Expected exit status ${expected}, got ${LAST_STATUS}.\n\n${LAST_OUTPUT}"
    return 1
  fi

  return 0
}

assert_contains() {
  local needle="$1"
  if [[ "${LAST_OUTPUT}" != *"${needle}"* ]]; then
    fail "Missing expected output: ${needle}\n\n${LAST_OUTPUT}"
    return 1
  fi

  return 0
}

assert_not_contains() {
  local needle="$1"
  if [[ "${LAST_OUTPUT}" == *"${needle}"* ]]; then
    fail "Unexpected output: ${needle}\n\n${LAST_OUTPUT}"
    return 1
  fi

  return 0
}

test_direct_startup() {
  echo -n "  Direct PTY startup:    "

  run_pty $'help\nhelp ls\nls\necho 21 * 2;\nexit\n' \
    env HOME="${HOME_DIR}" XDG_CONFIG_HOME="${CONFIG_DIR}" TERM=xterm-256color \
    php "${BIN_PATH}" -c "${CONFIG_FILE}" --no-pager --no-trust-project

  assert_status 0 &&
    assert_contains 'Show a list of commands' &&
    assert_contains 'Usage:' &&
    assert_contains 'List local, instance or class variables, methods and constants.' &&
    assert_contains '42' &&
    assert_not_contains 'Command "help" is not defined' &&
    assert_not_contains 'Undefined constant "ls"' &&
    pass
}

test_exit_code_path() {
  echo -n "  exit command vs code:  "

  run_pty $'exit;\n' \
    env HOME="${HOME_DIR}" XDG_CONFIG_HOME="${CONFIG_DIR}" TERM=xterm-256color \
    php "${BIN_PATH}" -c "${CONFIG_FILE}" --no-pager --no-trust-project

  assert_status 0 &&
    assert_not_contains 'Command "exit;" is not defined' &&
    assert_not_contains 'ParseError' &&
    pass
}

test_composer_proxy_startup() {
  echo -n "  Composer proxy PTY:    "

  run_pty $'help\nexit\n' \
    env HOME="${HOME_DIR}" XDG_CONFIG_HOME="${CONFIG_DIR}" TERM=xterm-256color \
    php "${PROXY_PROJECT}/vendor/bin/psysh" -c "${CONFIG_FILE}" --no-pager --cwd="${PROXY_PROJECT}"

  assert_status 0 &&
    assert_contains 'Show a list of commands' &&
    assert_contains 'End the current session and return to caller' &&
    pass
}

test_trust_flags() {
  echo -n "  Trust mode flags:      "

  run_php env HOME="${HOME_DIR}" XDG_CONFIG_HOME="${CONFIG_DIR}" TERM=dumb \
    php "${TRUST_RUNNER}" "${ROOT_DIR}/vendor/autoload.php" "${BIN_PATH}" "${PROXY_PROJECT}" flag --trust-project
  assert_status 0 &&
    assert_contains '"trust":"true"' &&
    assert_contains '"untrusted":false' || return 1

  run_php env HOME="${HOME_DIR}" XDG_CONFIG_HOME="${CONFIG_DIR}" TERM=dumb \
    php "${TRUST_RUNNER}" "${ROOT_DIR}/vendor/autoload.php" "${BIN_PATH}" "${PROXY_PROJECT}" flag --no-trust-project
  assert_status 0 &&
    assert_contains '"trust":"false"' &&
    # json_encode escapes forward slashes by default
    assert_contains "\"untrusted\":\"${PROXY_PROJECT//\//\\/}\"" || return 1

  run_php env HOME="${HOME_DIR}" XDG_CONFIG_HOME="${CONFIG_DIR}" TERM=dumb \
    php "${TRUST_RUNNER}" "${ROOT_DIR}/vendor/autoload.php" "${BIN_PATH}" "${PROXY_PROJECT}" env true
  assert_status 0 &&
    assert_contains '"trust":"true"' &&
    assert_contains '"untrusted":false' || return 1

  run_php env HOME="${HOME_DIR}" XDG_CONFIG_HOME="${CONFIG_DIR}" TERM=dumb \
    php "${TRUST_RUNNER}" "${ROOT_DIR}/vendor/autoload.php" "${BIN_PATH}" "${PROXY_PROJECT}" env false
  assert_status 0 &&
    assert_contains '"trust":"false"' &&
    # json_encode escapes forward slashes by default
    assert_contains "\"untrusted\":\"${PROXY_PROJECT//\//\\/}\"" &&
    pass
}

test_pager_lifecycle() {
  echo -n "  Pager lifecycle:       "

  run_pty $'help\nq\necho 42;\nexit\n' \
    env HOME="${HOME_DIR}" XDG_CONFIG_HOME="${CONFIG_DIR}" TERM=xterm-256color \
    php "${BIN_PATH}" -c "${CONFIG_FILE}" --pager="${PAGER_SCRIPT}" --no-trust-project

  assert_status 0 &&
    assert_contains 'Show a list of commands' &&
    assert_contains '42' &&
    assert_not_contains 'Broken pipe' &&
    pass
}

echo "PsySH PTY Smoke Tests (${TARGET})"
test_direct_startup || true
test_exit_code_path || true
test_composer_proxy_startup || true
test_trust_flags || true
test_pager_lifecycle || true

if [[ "${FAILED}" -ne 0 ]]; then
  exit 1
fi
