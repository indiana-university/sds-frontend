#!/usr/bin/env sh

# Build and start the local Omeka S stack with the local test environment.
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

# Required first argument: path to the environment file
if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <env-file> [docker compose args...]" >&2
    exit 1
fi
env_file="$1"
shift

if [ ! -f "$env_file" ]; then
    echo "Environment file not found: $env_file" >&2
    exit 1
fi

cd "$script_dir"
exec env SDSDB_ENV_FILE="$env_file" docker compose --env-file "$env_file" up --build "$@"
