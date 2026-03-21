#!/usr/bin/env bash

# This file is part of Psy Shell.
#
# (c) 2012-2026 Justin Hileman
#
# For the full copyright and license information, please view the LICENSE
# file that was distributed with this source code.

psysh_test_env_prepare() {
  local root="$1"

  export HOME="${root}/home"
  export XDG_CONFIG_HOME="${HOME}/.config"
  export XDG_DATA_HOME="${HOME}/.local/share"
  export XDG_RUNTIME_DIR="${root}/runtime"
  export XDG_CONFIG_DIRS="${root}/config-dirs"
  export XDG_DATA_DIRS="${root}/data-dirs"

  mkdir -p \
    "${XDG_CONFIG_HOME}/psysh" \
    "${XDG_DATA_HOME}/psysh" \
    "${XDG_RUNTIME_DIR}" \
    "${XDG_CONFIG_DIRS}" \
    "${XDG_DATA_DIRS}"

  unset PSYSH_CONFIG
  unset PSYSH_TRUST_PROJECT
  unset PSYSH_UNTRUSTED_PROJECT
}
