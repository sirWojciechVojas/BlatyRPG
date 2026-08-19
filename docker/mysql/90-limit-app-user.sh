#!/bin/sh
set -eu

case "${MYSQL_DATABASE:-}" in
    ''|*[!A-Za-z0-9_]*) exit 1 ;;
esac

case "${MYSQL_USER:-}" in
    ''|*[!A-Za-z0-9_]*) exit 1 ;;
esac

MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql --protocol=socket -uroot <<SQL
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${MYSQL_USER}'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
ON ${MYSQL_DATABASE}.*
TO '${MYSQL_USER}'@'%';
SQL
