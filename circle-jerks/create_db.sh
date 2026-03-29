#!/usr/bin/env sh
set -eu

# Usage:
#   DB_USER=root DB_PASS=secret DB_HOST=127.0.0.1 ./create_db.sh

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
SQL_FILE="$SCRIPT_DIR/create_db.sql"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

if [ ! -f "$SQL_FILE" ]; then
  echo "Missing SQL file: $SQL_FILE" >&2
  exit 1
fi

if [ -n "$DB_PASS" ]; then
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" < "$SQL_FILE"
else
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" < "$SQL_FILE"
fi

echo "Database and tables created (or already existed): circle_jerks"
