#!/bin/sh
# Bring the database current and recreate the public/ links and the addon boot cache on every container
# start. public/ is part of the image layer, so links made at runtime are gone after a redeploy, while
# what they point at lives in the persistent storage volume.
#
# - phpvms:update (every container but a worker, i.e. unless AUTORUN_ENABLED=false):
#   maintenance mode, InnoDB conversion, migrations, seeds, data migrations, Passport keys,
#   storage link, cache rebuild and Octane reload, as one run. Dockerfile.prod turns off the
#   base image's own AUTORUN migrate and optimize, so this is the only place they happen.
# - optimize (same containers): phpvms:update skips its cache step when the database is
#   already current, and the base image's AUTORUN optimize is off.
# - Worker containers (AUTORUN_ENABLED=false) do not update. They wait until
#   phpvms:update --check says the database is current, so the addon cache below is built after the
#   migrations. They wait up to PHPVMS_WORKER_WAIT_TIMEOUT seconds (default 300), then start anyway.
#   The app container waits up to PHPVMS_UPDATE_WAIT seconds (default 300) for another update's lock.
# - public/storage -> storage/app/public: the public disk, and theme addon
#   assets under storage/app/public/themes.
# - storage/app/addons/<name> -> ../../../modules/<name>: the bundled addons
#   (phpvms:addons-link-bundled). The storage volume is filled from the image
#   only once, so an addon bundled by a later image has no link until this
#   runs. It runs before addons:cache so the cache finds it.
# - bootstrap/cache/addons.php: the addon boot cache (addons:cache). It is not
#   in the image, and Octane workers read it before they would build it, so
#   without this the first workers start with no addons or theme addons.
# - public/ext/<addon>: each enabled addon's public/ (addons:relink).
#
# The last two need the database, so a start without one logs the failure and
# carries on.
#
# No step may stop the container: a failed update is logged and shown again in
# /admin, and a missing link or cache costs assets or addons, not the app.
if [ "$AUTORUN_ENABLED" != "false" ]; then
    php artisan phpvms:update --wait="${PHPVMS_UPDATE_WAIT:-300}" || echo "phpvms: phpvms:update failed"
    php artisan optimize || echo "phpvms: optimize failed"
else
    limit="${PHPVMS_WORKER_WAIT_TIMEOUT:-300}"
    deadline=$(( $(date +%s) + limit ))
    while :; do
        remaining=$(( deadline - $(date +%s) ))
        [ "$remaining" -gt 0 ] || remaining=1
        # Bound the check too, so a hung database cannot outlast the deadline.
        if command -v timeout >/dev/null 2>&1; then
            timeout "$remaining" php artisan phpvms:update --check --db-timeout=1 >/dev/null 2>&1 && break
        else
            php artisan phpvms:update --check --db-timeout=1 >/dev/null 2>&1 && break
        fi
        if [ "$(date +%s)" -ge "$deadline" ]; then
            echo "phpvms: the database is not current after ${limit}s, starting anyway"
            break
        fi
        sleep 5
    done
fi

php artisan storage:link --relative || echo "phpvms: storage:link failed"
php artisan phpvms:addons-link-bundled || echo "phpvms: phpvms:addons-link-bundled failed"
php artisan addons:cache || echo "phpvms: addons:cache failed"
php artisan addons:relink || echo "phpvms: addons:relink failed"
