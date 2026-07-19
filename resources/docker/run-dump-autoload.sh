#!/bin/sh
# Regenerate the optimised autoloader to pick up any modules added to the
# phpvms_modules volume at runtime.
#
# --no-scripts: skip post-autoload-dump hooks (ide-helper:generate etc. are
# dev-only and not installed in production; running them logs a spurious
# NamespaceNotFoundException to the application log).
# -o (optimised) only — NOT -a/--classmap-authoritative. Authoritative mode
# causes AddonServiceProvider to throw AutoloadModeException on every worker
# boot (it guards against classmap-authoritative so addon PSR-4 registration works).
composer dump-autoload -o --no-scripts

# Run package:discover manually so bootstrap/cache/packages.php stays current
# (this is the only production-relevant step from post-autoload-dump).
php artisan package:discover --ansi
