#!/usr/bin/env bash
# Linux standalone packaging; validate provenance before sending a Docker context.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
source_sha="$(python3 build/source.py --source-sha "${ECHO_SOURCE_SHA:-$(git rev-parse HEAD)}")"
exec docker buildx build --file docker/standalone/Dockerfile \
  --target linux-artifacts --output type=local,dest=dist/assets \
  --build-arg "ECHO_SOURCE_SHA=${source_sha}" \
  --build-arg "ECHO_RELEASE_VERSION=${ECHO_RELEASE_VERSION:-}" .
