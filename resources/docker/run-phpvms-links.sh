#!/bin/sh
# Recreate the public/ links on every container start. public/ is part of the
# image layer, so links made at runtime are gone after a redeploy, while what
# they point at lives in the persistent storage volume.
#
# - public/storage -> storage/app/public: the public disk, and theme addon
#   assets under storage/app/public/themes. serversideup's AUTORUN also runs
#   storage:link, but only when AUTORUN_ENABLED is true.
# - public/ext/<addon>: each enabled addon's public/ (addons:relink). Needs the
#   database, so a start without one logs the failure and carries on.
#
# Neither step may stop the container: a missing link breaks assets, not the
# app, and the admin can still fix it.
php artisan storage:link --relative || echo "phpvms: storage:link failed"
php artisan addons:relink || echo "phpvms: addons:relink failed"
