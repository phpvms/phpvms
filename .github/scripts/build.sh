#!/usr/bin/env bash
#
# Strip the checkout down to what ships and package it as a tarball and zip
# into dist/. Expects the variables exported by version.sh.
#
set -euo pipefail

echo "Version: ${VERSION}"
echo "Package name: ${TAR_NAME}"
echo "Current directory: ${BASE_DIR}"

echo "Cleaning files"

# Leftover individual files to delete
declare -a remove_files=(
  .git
  .github
  .sass-cache
  .idea
  .travis
  docker
  node_modules
  vendor/willdurand/geocoder/tests
  _ide_helper.php
  .env
  .dockerignore
  .dpl
  .editorconfig
  .eslintignore
  .eslintrc
  .php_cs
  .php_cs.cache
  .php-cs-fixer.php
  .phpstorm.meta.php
  .styleci.yml
  .phpunit.result.cache
  env.php
  intellij_style.xml
  config.php
  Makefile
  phpcs.xml
  phpunit.xml
  phpvms.iml
  phpstan.neon
  pint.json
  Procfile
  swagger.yml
  composer.phar
  version.json
)

for file in "${remove_files[@]}"; do
  rm -rf "$file"
done

find ./vendor -type d -name ".git" -print0 | xargs rm -rf
find . -type d -name "sass-cache" -print0 | xargs rm -rf

# Empty the writable dirs of anything the build left behind, keeping their
# .gitignore and the subdirs listed after the path
clean_dir() {
  local dir=$1; shift
  [ -d "$dir" ] || return 0
  local keep=(-not -name '.gitignore')
  for name in "$@"; do
    keep+=(-not -name "$name")
  done
  find "$dir" -mindepth 1 -maxdepth 1 "${keep[@]}" -exec rm -rf {} +
}

clean_dir bootstrap/cache
clean_dir storage/app public import
clean_dir storage/app/public avatars uploads
clean_dir storage/app/public/avatars
clean_dir storage/app/public/uploads
clean_dir storage/debugbar
clean_dir storage/docker
clean_dir storage/framework/cache
clean_dir storage/framework/sessions
clean_dir storage/framework/views
clean_dir storage/logs

mkdir -p storage/app/public/avatars
mkdir -p storage/app/public/uploads
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views

# Package into a scratch dir so the archives don't include themselves
STAGE=$(mktemp -d)

# Archive the top-level entries by name so paths have no ./ prefix
mapfile -t entries < <(cd "$BASE_DIR" && ls -A)
tar -czf "$STAGE/$TAR_NAME" -C "$BASE_DIR" "${entries[@]}"
(cd "$BASE_DIR" && zip -qr "$STAGE/$ZIP_NAME" ./)

cd "$STAGE"
sha256sum "$TAR_NAME" > "$TAR_NAME.sha256"
sha256sum "$ZIP_NAME" > "$ZIP_NAME.sha256"

mkdir -p "$BASE_DIR/dist"
mv "$TAR_NAME" "$ZIP_NAME" "$TAR_NAME.sha256" "$ZIP_NAME.sha256" "$BASE_DIR/dist/"
ls -al "$BASE_DIR/dist"
