#!/bin/bash

# Script to import SQL dump files into a MySQL database
# Usage: ./import_database.sh <env_file> <sql_folder>

set -e

# MySQL client path
MYSQL_CMD="/opt/homebrew/opt/mysql-client/bin/mysql"

# Check if correct number of arguments provided
if [ "$#" -ne 2 ]; then
    echo "Usage: $0 <env_file> <sql_folder>"
    echo "  env_file: Path to environment file containing database connection info"
    echo "  sql_folder: Path to folder containing *.sql dump files"
    exit 1
fi

ENV_FILE="$1"
SQL_FOLDER="$2"

# Check if env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "Error: Environment file '$ENV_FILE' not found"
    exit 1
fi

# Check if SQL folder exists
if [ ! -d "$SQL_FOLDER" ]; then
    echo "Error: SQL folder '$SQL_FOLDER' not found"
    exit 1
fi

# Load environment variables from the env file
echo "Loading database configuration from $ENV_FILE..."
export $(grep -v '^#' "$ENV_FILE" | grep -v '^$' | xargs)

# Validate required environment variables
if [ -z "$MYSQL_HOST" ] || [ -z "$MYSQL_DATABASE" ] || [ -z "$MYSQL_USER" ] || [ -z "$MYSQL_PASSWORD" ]; then
    echo "Error: Missing required environment variables"
    echo "Required: MYSQL_HOST, MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD"
    exit 1
fi

echo "Database configuration:"
echo "  Host: $MYSQL_HOST"
echo "  Database: $MYSQL_DATABASE"
echo "  User: $MYSQL_USER"
echo ""

# Test database connection
echo "Testing database connection..."
if ! "$MYSQL_CMD" -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "USE $MYSQL_DATABASE;" 2>/dev/null; then
    echo "Error: Cannot connect to database"
    exit 1
fi
echo "Connection successful!"
echo ""

# Count SQL files
SQL_FILES=("$SQL_FOLDER"/*.sql)
if [ ! -e "${SQL_FILES[0]}" ]; then
    echo "Error: No *.sql files found in $SQL_FOLDER"
    exit 1
fi

FILE_COUNT=$(ls -1 "$SQL_FOLDER"/*.sql 2>/dev/null | wc -l)
echo "Found $FILE_COUNT SQL file(s) to import"
echo ""

# Import each SQL file
SUCCESS_COUNT=0
FAIL_COUNT=0

for sql_file in "$SQL_FOLDER"/*.sql; do
    if [ -f "$sql_file" ]; then
        filename=$(basename "$sql_file")
        echo "Importing $filename..."
        
        # Capture error output
        ERROR_OUTPUT=$(mktemp)
        if "$MYSQL_CMD" -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < "$sql_file" 2>"$ERROR_OUTPUT"; then
            echo "  ✓ Successfully imported $filename"
            SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
        else
            echo "  ✗ Failed to import $filename"
            echo "  Error details:"
            cat "$ERROR_OUTPUT" | sed 's/^/    /'
            FAIL_COUNT=$((FAIL_COUNT + 1))
        fi
        rm -f "$ERROR_OUTPUT"
        echo ""
    fi
done

# Summary
echo "========================================"
echo "Import Summary:"
echo "  Total files: $FILE_COUNT"
echo "  Successful: $SUCCESS_COUNT"
echo "  Failed: $FAIL_COUNT"
echo "========================================"

if [ $FAIL_COUNT -gt 0 ]; then
    exit 1
fi

echo "All imports completed successfully!"
