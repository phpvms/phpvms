# phpVMS task runner
#
# Install just:  brew install just  (or cargo install just)
# List recipes:  just --list

set shell := ["bash", "-cu"]

# Default: list all recipes
default:
    @just --list

# ── Frontend ────────────────────────────────────────────────────────────────

# Vite dev server (theme + admin map bundle, HMR)
dev:
    npm run dev

# Watch Alpine components (esbuild --dev, inline sourcemaps)
dev-components:
    npm run dev:components

# Production build: Vite (theme + admin) + esbuild (Alpine components) + Filament asset publish
build:
    npm run build
    php artisan filament:assets

# Vite only — theme + admin map bundle
build-vite:
    npm run build

# esbuild only — Filament AlpineComponent bundles
build-components:
    npm run build:components
    php artisan filament:assets

# Publish Filament assets into /public (AlpineComponents, plugin CSS/JS)
publish:
    php artisan filament:assets

# ── Linting / Formatting ────────────────────────────────────────────────────

# JS lint (oxlint)
lint-js:
    npm run lint

lint-js-fix:
    npm run lint:fix

# JS format (oxfmt)
fmt-js:
    npm run fmt

fmt-js-check:
    npm run fmt:check

# PHP format (Pint, dirty files only)
fmt-php:
    vendor/bin/pint --dirty

fmt-php-check:
    vendor/bin/pint --test

# ── Static Analysis ─────────────────────────────────────────────────────────

# PHPStan / Larastan (level 5)
stan:
    vendor/bin/phpstan analyse

# Rector dry-run (no writes)
rector-dry:
    vendor/bin/rector --dry-run

# Rector apply (writes changes)
rector:
    vendor/bin/rector

# ── Testing ─────────────────────────────────────────────────────────────────

# Full Pest suite
test:
    composer test

# Filter tests: `just test-filter PirepTest`
test-filter filter:
    php artisan test --compact --filter={{filter}}

# ── Pre-PR Checklist (matches AGENTS.md) ────────────────────────────────────

# Run all four gates: pint, phpstan, pest, rector
check:
    composer pint --test
    vendor/bin/phpstan analyse
    composer test
    vendor/bin/rector --dry-run
