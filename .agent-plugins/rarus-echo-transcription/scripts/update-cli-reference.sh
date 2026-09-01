#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${PLUGIN_DIR}/../.." && pwd)"
OUTPUT="${PLUGIN_DIR}/skills/transcribe/references/cli.md"

if [[ "${1:-}" == "--output" ]]; then
  if [[ $# -ne 2 ]]; then
    echo "usage: $0 [--output <path>]" >&2
    exit 64
  fi
  OUTPUT="$2"
elif [[ $# -ne 0 ]]; then
  echo "usage: $0 [--output <path>]" >&2
  exit 64
fi

host_php_is_compatible() {
  if ! command -v php >/dev/null 2>&1; then
    return 1
  fi

  local version_id
  version_id="$(php -r 'echo PHP_VERSION_ID;' 2>/dev/null || true)"

  [[ "${version_id}" =~ ^[0-9]+$ && "${version_id}" -ge 80401 ]]
}

run_cli() {
  if host_php_is_compatible && [[ -x "${REPO_ROOT}/vendor/bin/rarus-echo" ]]; then
    (cd "${REPO_ROOT}" && "${REPO_ROOT}/vendor/bin/rarus-echo" "$@")
    return
  fi

  if host_php_is_compatible && [[ -f "${REPO_ROOT}/vendor/autoload.php" ]]; then
    (cd "${REPO_ROOT}" && php bin/rarus-echo "$@")
    return
  fi

  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    (cd "${REPO_ROOT}" && COMPOSE_IGNORE_ORPHANS=True docker compose run --rm -T php-cli php bin/rarus-echo "$@")
    return
  fi

  echo "Unable to run rarus-echo help. Install dependencies or enable Docker Compose." >&2
  exit 69
}

if ! command -v node >/dev/null 2>&1; then
  echo "Unable to format CLI reference. Install Node.js." >&2
  exit 69
fi

PROJECT_COMMANDS=(queue submit status transcript)
TMP_DIR="$(mktemp -d)"
TMP_OUTPUT="$(mktemp)"

cleanup() {
  rm -rf "${TMP_DIR}" "${TMP_OUTPUT}"
}
trap cleanup EXIT

run_cli list --format=json > "${TMP_DIR}/list.json"
for command in "${PROJECT_COMMANDS[@]}"; do
  run_cli help "${command}" --format=json > "${TMP_DIR}/${command}.json"
done

node - "${TMP_DIR}" > "${TMP_OUTPUT}" <<'NODE'
const fs = require("fs");
const path = require("path");

const tmpDir = process.argv[2];
const projectCommands = ["queue", "submit", "status", "transcript"];
const optionAllowlist = {
  queue: ["json"],
  submit: [
    "json",
    "task-type",
    "language",
    "censor",
    "speakers-correction",
    "timestamps-extended",
    "no-store-file",
    "low-priority",
    "request-source",
    "wait",
    "poll-interval",
    "timeout",
    "raw-result",
    "output",
  ],
  status: ["json"],
  transcript: ["json"],
};

function fail(message) {
  console.error(message);
  process.exit(1);
}

function readJson(file) {
  try {
    return JSON.parse(fs.readFileSync(path.join(tmpDir, file), "utf8"));
  } catch (error) {
    fail(`Unable to read ${file}: ${error.message}`);
  }
}

function values(record) {
  if (!record) {
    return [];
  }
  return Array.isArray(record) ? record : Object.values(record);
}

function cleanText(value) {
  return String(value ?? "")
    .replace(/<[^>]+>/g, "")
    .replace(/\s+/g, " ")
    .trim();
}

function tableText(value) {
  return cleanText(value).replace(/\|/g, "\\|");
}

function code(value) {
  return `\`${String(value).replace(/`/g, "\\`")}\``;
}

function defaultValue(value) {
  if (typeof value === "undefined") {
    return code("undefined");
  }
  return code(JSON.stringify(value));
}

function yesNo(value) {
  return value ? "yes" : "no";
}

function commandUsage(help) {
  if (!Array.isArray(help.usage) || help.usage.length === 0) {
    fail(`Command ${help.name} does not expose usage metadata`);
  }
  return help.usage.map((line) => String(line));
}

const list = readJson("list.json");
const listCommands = values(list.commands);
const listedByName = new Map(listCommands.map((command) => [command.name, command]));
const helpByName = new Map();

for (const name of projectCommands) {
  if (!listedByName.has(name)) {
    fail(`Command ${name} is missing from rarus-echo list metadata`);
  }
  const help = readJson(`${name}.json`);
  if (!help.definition) {
    fail(`Command ${name} does not expose definition metadata`);
  }
  helpByName.set(name, help);
}

const lines = [];
lines.push("# RARUS Echo CLI Reference");
lines.push("");
lines.push("This file is generated from current checkout structured CLI metadata.");
lines.push("It intentionally records only RARUS Echo command contracts and excludes framework-provided Symfony options.");
lines.push("");
lines.push("Refresh it after CLI command or option changes:");
lines.push("");
lines.push("```bash");
lines.push(".agent-plugins/rarus-echo-transcription/scripts/update-cli-reference.sh");
lines.push("```");
lines.push("");
lines.push("## Commands");
lines.push("");
lines.push("| Command | Description |");
lines.push("| --- | --- |");

for (const name of projectCommands) {
  const command = listedByName.get(name);
  lines.push(`| ${code(name)} | ${tableText(command.description)} |`);
}

for (const name of projectCommands) {
  const help = helpByName.get(name);
  const definition = help.definition;
  lines.push("");
  lines.push(`## ${name}`);
  lines.push("");
  lines.push(tableText(help.description || help.help || listedByName.get(name).description));
  lines.push("");
  lines.push("### Usage");
  lines.push("");
  lines.push("```text");
  for (const usage of commandUsage(help)) {
    lines.push(usage);
  }
  lines.push("```");
  lines.push("");
  lines.push("### Arguments");
  lines.push("");

  const args = values(definition.arguments);
  if (args.length === 0) {
    lines.push("None.");
  } else {
    lines.push("| Argument | Required | Multiple | Default | Description |");
    lines.push("| --- | --- | --- | --- | --- |");
    for (const argument of args) {
      lines.push(
        `| ${code(argument.name)} | ${yesNo(argument.is_required)} | ${yesNo(argument.is_array)} | ${defaultValue(argument.default)} | ${tableText(argument.description)} |`,
      );
    }
  }

  lines.push("");
  lines.push("### Options");
  lines.push("");
  lines.push("| Option | Accepts Value | Value Required | Multiple | Default | Description |");
  lines.push("| --- | --- | --- | --- | --- | --- |");

  const options = definition.options || {};
  for (const optionName of optionAllowlist[name]) {
    const option = options[optionName];
    if (!option) {
      fail(`Command ${name} is missing expected option --${optionName}`);
    }
    lines.push(
      `| ${code(option.name)} | ${yesNo(option.accept_value)} | ${yesNo(option.is_value_required)} | ${yesNo(option.is_multiple)} | ${defaultValue(option.default)} | ${tableText(option.description)} |`,
    );
  }
}

process.stdout.write(`${lines.join("\n")}\n`);
NODE

mkdir -p "$(dirname "${OUTPUT}")"
mv "${TMP_OUTPUT}" "${OUTPUT}"
trap - EXIT
cleanup
