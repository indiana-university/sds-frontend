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

echo "Testing database connection..."
tables=$("$MYSQL_CMD" -h "$MYSQL_HOST" ${MYSQL_PORT:+-P "$MYSQL_PORT"} -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --connect-timeout=10 \
    -N -e "USE \`$MYSQL_DATABASE\`; SHOW TABLES;" 2>/dev/null) || {
    echo "Error: Cannot connect to database" >&2
    exit 1
}
echo "Connection successful!"
echo
tables_count=$(printf '%s\n' "$tables" | grep -c . || true)
echo "Number of tables in \`$MYSQL_DATABASE\`: $tables_count"

# If the user table exists, report its row count
if [ -n "$tables" ] && printf '%s\n' "$tables" | grep -qx 'user'; then
    echo
    user_count=$("$MYSQL_CMD" -h "$MYSQL_HOST" ${MYSQL_PORT:+-P "$MYSQL_PORT"} -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" \
        -N -e "SELECT COUNT(*) FROM \`$MYSQL_DATABASE\`.\`user\`;" 2>/dev/null) || {
        echo "Error: Failed to count rows in \`user\`" >&2
        exit 1
    }
    echo "Entries in \`user\`: $user_count"
fi

# If the module table exists, report its row count
if [ -n "$tables" ] && printf '%s\n' "$tables" | grep -qx 'module'; then
    echo
    module_count=$("$MYSQL_CMD" -h "$MYSQL_HOST" ${MYSQL_PORT:+-P "$MYSQL_PORT"} -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" \
        -N -e "SELECT COUNT(*) FROM \`$MYSQL_DATABASE\`.\`module\`;" 2>/dev/null) || {
        echo "Error: Failed to count rows in \`module\`" >&2
        exit 1
    }
    echo "Entries in \`module\`: $module_count"
fi
