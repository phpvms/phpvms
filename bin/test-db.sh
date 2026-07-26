#!/usr/bin/env bash
#
# Run the test suite against a real database server on a scratch database that
# is created for the run and dropped afterwards.
#
#   bin/test-db.sh pgsql
#   bin/test-db.sh mysql tests/Feature/ImporterTest.php
#
# The scratch database matters. compose.test.yml ships a database called
# `testing`, but a local .env may point the development app at that same name on
# the same container -- and the suite runs RefreshDatabase, which would drop the
# schema out from under a running app. Using a name nothing else claims keeps
# the two apart.
#
# The name is fixed rather than per-run because phpunit.<driver>.xml carries the
# connection string, and those entries are force="true" precisely so an exported
# variable cannot redirect the suite. Any leftover from a crashed run is dropped
# on the way in.
set -euo pipefail

DB=phpvms_suite

driver="${1:-}"
if [ -z "$driver" ]; then
    echo "usage: bin/test-db.sh <mysql|pgsql> [pest arguments...]" >&2
    exit 2
fi
shift

case "$driver" in
    pgsql)
        container=phpvms-test-pgsql-1
        psql() { docker exec "$container" psql -U phpvms -d postgres -q "$@"; }
        create() { psql -c "DROP DATABASE IF EXISTS $DB WITH (FORCE);" -c "CREATE DATABASE $DB OWNER phpvms;"; }
        # WITH (FORCE) so an abandoned connection cannot pin the database and
        # leave it behind for the next run to trip over.
        cleanup() { psql -c "DROP DATABASE IF EXISTS $DB WITH (FORCE);" >/dev/null 2>&1 || true; }
        ;;
    mysql)
        container=phpvms-test-mysql-1
        my() { docker exec "$container" mysql -uroot -ppassword "$@"; }
        create() { my -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\`;"; }
        cleanup() { my -e "DROP DATABASE IF EXISTS \`$DB\`;" >/dev/null 2>&1 || true; }
        ;;
    *)
        echo "unknown driver '$driver' (expected mysql or pgsql)" >&2
        exit 2
        ;;
esac

# On .State.Running, not on `docker inspect` succeeding: a container that
# exists but is stopped inspects just fine, and the raw "cannot exec in a
# stopped container" from the first docker exec is not the help this prints.
if [ "$(docker inspect -f '{{.State.Running}}' "$container" 2>/dev/null)" != "true" ]; then
    echo "container $container is not running -- start it with:" >&2
    echo "    docker compose -f compose.test.yml up -d $driver" >&2
    exit 1
fi

# EXIT alone owns the cleanup, so it runs exactly once -- listing INT and TERM
# alongside it would fire the handler on the signal and again on the way out.
# The signal traps just exit with the conventional 128+signo, which is what
# gets the EXIT trap there. An exit code the script does not set itself is
# untouched by this, so pest's status still propagates.
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

create
vendor/bin/pest -c "phpunit.$driver.xml" "$@"
