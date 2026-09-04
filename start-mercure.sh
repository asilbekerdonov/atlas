#!/usr/bin/env bash
# Starts the local Mercure hub (dev, no Docker required) on port 3000.
# Requirements: a mercure binary — either `brew install dunglas/tap/mercure`
# or the pre-downloaded copy in var/mercure/mercure (used automatically).
set -euo pipefail
cd "$(dirname "$0")/.."

if curl -sf -o /dev/null http://127.0.0.1:3000/healthz; then
    echo "Mercure hub is already running on http://127.0.0.1:3000"
    exit 0
fi

MERCURE_BIN="$(command -v mercure || true)"
if [ -z "$MERCURE_BIN" ]; then
    if [ -x var/mercure/mercure ]; then
        MERCURE_BIN="$(pwd)/var/mercure/mercure"
    else
        echo "mercure binary not found. Install it: brew install dunglas/tap/mercure"
        exit 1
    fi
fi

mkdir -p var/mercure/caddy-data
echo "Starting Mercure hub on http://127.0.0.1:3000 ..."
XDG_DATA_HOME="$(pwd)/var/mercure/caddy-data" "$MERCURE_BIN" run --config var/mercure/Caddyfile
