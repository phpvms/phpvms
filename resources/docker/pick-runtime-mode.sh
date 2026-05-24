#!/bin/sh
# Selects the runtime mode for the phpVMS app container.
#
# Defaults to Octane (FrankenPHP worker mode). Setting
# PHPVMS_OCTANE_ENABLED=false (or 0) falls back to the Serversideup default
# FrankenPHP entrypoint, which serves each request through a stock PHP worker
# pool (matches the previous PHP-FPM request semantics).
#
# Any value other than "false" or "0" (case-insensitive) — including unset —
# means Octane is enabled.

mode="${PHPVMS_OCTANE_ENABLED:-true}"

case "$(printf '%s' "$mode" | tr '[:upper:]' '[:lower:]')" in
    false|0)
        # Classic mode — fall through to Serversideup's default web entrypoint.
        exit 0
        ;;
    *)
        # Octane worker mode — replace this process with the Octane server.
        exec php /var/www/html/artisan octane:frankenphp \
            --host=0.0.0.0 \
            --port=80 \
            --workers=auto \
            --max-requests=500
        ;;
esac
