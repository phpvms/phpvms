#!/bin/sh
# Recreate the public/ links and the addon boot cache on every container start. public/ is part of the
# image layer, so links made at runtime are gone after a redeploy, while what
# they point at lives in the persistent storage volume.
#
# - public/storage -> storage/app/public: the public disk, and theme addon
#   assets under storage/app/public/themes. serversideup's AUTORUN also runs
#   storage:link, but only when AUTORUN_ENABLED is true.
# - bootstrap/cache/addons.php: the addon boot cache (addons:cache). It is not
#   in the image, and Octane workers read it before they would build it, so
#   without this the first workers start with no addons or theme addons.
# - public/ext/<addon>: each enabled addon's public/ (addons:relink).
#
# The last two need the database, so a start without one logs the failure and
# carries on.
#
# No step may stop the container: a missing link or cache costs assets or
# addons, not the app, and the admin can still fix it.
php artisan storage:link --relative || echo "phpvms: storage:link failed"
php artisan addons:cache || echo "phpvms: addons:cache failed"
php artisan addons:relink || echo "phpvms: addons:relink failed"
