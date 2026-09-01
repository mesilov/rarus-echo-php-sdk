#!/usr/bin/env bash

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${PLUGIN_DIR}/../.." && pwd)"
PLUGIN_NAME="rarus-echo-transcription"
SKILL_NAME="transcribe"
ERRORS=0

fail() {
  echo "ERROR: $*" >&2
  ERRORS=$((ERRORS + 1))
}

relpath() {
  local path="$1"
  printf '%s' "${path#"${REPO_ROOT}/"}"
}

require_file() {
  local path="$1"
  if [[ ! -f "${path}" ]]; then
    fail "missing file: $(relpath "${path}")"
    return 1
  fi
  return 0
}

require_executable() {
  local path="$1"
  if [[ ! -x "${path}" ]]; then
    fail "missing executable: $(relpath "${path}")"
    return 1
  fi
  return 0
}

validate_json() {
  local path="$1"
  if ! node -e 'const fs = require("fs"); JSON.parse(fs.readFileSync(process.argv[1], "utf8"));' "${path}" >/dev/null 2>&1; then
    fail "invalid JSON: $(relpath "${path}")"
    return 1
  fi
  return 0
}

validate_plugin_manifest() {
  local path="$1"
  if ! require_file "${path}" || ! validate_json "${path}"; then
    return
  fi

  if ! node -e '
const fs = require("fs");
const manifest = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
if (manifest.name !== process.argv[2]) process.exit(1);
if (manifest.skills !== "./skills/") process.exit(2);
' "${path}" "${PLUGIN_NAME}" >/dev/null 2>&1; then
    fail "plugin manifest must name ${PLUGIN_NAME} and expose ./skills/: $(relpath "${path}")"
  fi
}

validate_marketplace() {
  local path="$1"
  local expected_source="$2"

  if ! require_file "${path}" || ! validate_json "${path}"; then
    return
  fi

  if ! node -e '
const fs = require("fs");
const marketplace = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
const pluginName = process.argv[2];
const expectedSource = process.argv[3];
const plugins = Array.isArray(marketplace.plugins) ? marketplace.plugins : [];
const entry = plugins.find((plugin) => plugin.name === pluginName);
if (!entry) process.exit(1);
const source = typeof entry.source === "string" ? entry.source : entry.source && entry.source.path;
if (source !== expectedSource) process.exit(2);
' "${path}" "${PLUGIN_NAME}" "${expected_source}" >/dev/null 2>&1; then
    fail "marketplace must list ${PLUGIN_NAME} with source ${expected_source}: $(relpath "${path}")"
  fi
}

validate_codex_marketplace_metadata() {
  local path="$1"
  if ! require_file "${path}" || ! validate_json "${path}"; then
    return
  fi

  if ! node -e '
const fs = require("fs");
const marketplace = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
const pluginName = process.argv[2];
const plugins = Array.isArray(marketplace.plugins) ? marketplace.plugins : [];
const entry = plugins.find((plugin) => plugin.name === pluginName);
if (!marketplace.interface || !marketplace.interface.displayName) process.exit(1);
if (!entry) process.exit(2);
if (!entry.policy || entry.policy.installation !== "AVAILABLE") process.exit(3);
if (!entry.policy || entry.policy.authentication !== "ON_INSTALL") process.exit(4);
if (!entry.category) process.exit(5);
' "${path}" "${PLUGIN_NAME}" >/dev/null 2>&1; then
    fail "Codex marketplace must include interface.displayName, policy, and category: $(relpath "${path}")"
  fi
}

validate_skill_frontmatter() {
  local path="$1"
  if ! require_file "${path}"; then
    return
  fi

  local frontmatter
  frontmatter="$(awk 'NR == 1 && $0 == "---" { inside = 1; next } inside && $0 == "---" { exit } inside { print }' "${path}")"

  if ! grep -qx "name: ${SKILL_NAME}" <<< "${frontmatter}"; then
    fail "skill frontmatter must include name: ${SKILL_NAME}: $(relpath "${path}")"
  fi

  if ! grep -qE '^description: .+' <<< "${frontmatter}"; then
    fail "skill frontmatter must include a non-empty description: $(relpath "${path}")"
  fi
}

validate_cli_reference() {
  local reference="${PLUGIN_DIR}/skills/${SKILL_NAME}/references/cli.md"
  local updater="${PLUGIN_DIR}/scripts/update-cli-reference.sh"

  require_file "${reference}" || return
  require_executable "${updater}" || return

  local generated
  generated="$(mktemp)"
  if ! "${updater}" --output "${generated}" >/tmp/rarus-echo-cli-reference-update.log 2>&1; then
    cat /tmp/rarus-echo-cli-reference-update.log >&2
    rm -f "${generated}" /tmp/rarus-echo-cli-reference-update.log
    fail "unable to regenerate CLI reference"
    return
  fi
  rm -f /tmp/rarus-echo-cli-reference-update.log

  if ! diff -u "${reference}" "${generated}" >/tmp/rarus-echo-cli-reference.diff; then
    cat /tmp/rarus-echo-cli-reference.diff >&2
    fail "structured CLI reference drift detected; run .agent-plugins/rarus-echo-transcription/scripts/update-cli-reference.sh"
  fi

  rm -f "${generated}" /tmp/rarus-echo-cli-reference.diff
}

validate_plugin_manifest "${PLUGIN_DIR}/.claude-plugin/plugin.json"
validate_plugin_manifest "${PLUGIN_DIR}/.codex-plugin/plugin.json"
validate_marketplace "${REPO_ROOT}/.claude-plugin/marketplace.json" "./.agent-plugins/${PLUGIN_NAME}"
validate_marketplace "${REPO_ROOT}/.agents/plugins/marketplace.json" "./.agent-plugins/${PLUGIN_NAME}"
validate_codex_marketplace_metadata "${REPO_ROOT}/.agents/plugins/marketplace.json"
validate_skill_frontmatter "${PLUGIN_DIR}/skills/${SKILL_NAME}/SKILL.md"
require_file "${PLUGIN_DIR}/skills/${SKILL_NAME}/references/distribution.md" >/dev/null
validate_cli_reference

if [[ ${ERRORS} -ne 0 ]]; then
  echo "Agent plugin validation failed with ${ERRORS} error(s)." >&2
  exit 1
fi

echo "Agent plugin validation passed."
