#!/usr/bin/env sh

# Test the MySQL connection using MYSQL_* parameters from an env file.
# Usage: ./test_db_connection.sh [env_file]
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

env_file="${1:-$script_dir/.nflperim.env}"

if [ ! -f "$env_file" ]; then
    echo "Error: Environment file not found: $env_file" >&2
    exit 1
fi

MYSQL_CMD="${MYSQL_CMD:-/opt/homebrew/opt/mysql-client/bin/mysql}"

# Load only the MYSQL_* variables from the env file
# (avoid sourcing the whole file, which may contain unquoted values)
for var in MYSQL_HOST MYSQL_PORT MYSQL_USER MYSQL_DATABASE MYSQL_PASSWORD; do
    value=$(grep -E "^${var}=" "$env_file" | tail -n1 | cut -d= -f2-)
    [ -n "$value" ] && export "$var=$value"
done

if [ -z "${MYSQL_HOST:-}" ] || [ -z "${MYSQL_USER:-}" ] || [ -z "${MYSQL_DATABASE:-}" ] || [ -z "${MYSQL_PASSWORD:-}" ]; then
    echo "Error: Missing required variables (MYSQL_HOST, MYSQL_USER, MYSQL_DATABASE, MYSQL_PASSWORD) in $env_file" >&2
    exit 1
fi

echo "Host:     $MYSQL_HOST${MYSQL_PORT:+:$MYSQL_PORT}"
echo "User:     $MYSQL_USER"
echo "Database: $MYSQL_DATABASE"
echo

if "$MYSQL_CMD" -h "$MYSQL_HOST" ${MYSQL_PORT:+-P "$MYSQL_PORT"} -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --connect-timeout=10 -e "USE \`$MYSQL_DATABASE\`; SELECT 1;" 2>/dev/null; then
    echo "Connection successful!"
else
    echo "Error: Cannot connect to database" >&2
    exit 1
fi
