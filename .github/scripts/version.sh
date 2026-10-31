#!/usr/bin/env bash
#
# Work out the version and package names for this build and export them for
# the following workflow steps.
#
# On a release the ref is the tag (8.0.0-alpha.1, 8.0.0, ...). It is stamped
# into the shipped composer.json, which is where VersionService reads the
# version from. Branch builds (nightlies) keep the committed placeholder.
#
set -euo pipefail

if [ "${GITHUB_REF_TYPE:-}" = "tag" ]; then
  composer config version "${GITHUB_REF_NAME}"
  composer update --lock --no-install --no-scripts --no-interaction
fi

VERSION=$(composer config version)
FILE_NAME="phpvms-${GITHUB_REF_NAME}"
TAR_NAME="${FILE_NAME}.tar.gz"
ZIP_NAME="${FILE_NAME}.zip"
BASE_DIR=$(pwd)

echo "VERSION=${VERSION}"
echo "FILE_NAME=${FILE_NAME}"
echo "BASE_DIR=${BASE_DIR}"

# https://docs.github.com/en/actions/reference/workflow-commands-for-github-actions#environment-files
{
  echo "VERSION=${VERSION}"
  echo "FILE_NAME=${FILE_NAME}"
  echo "TAR_NAME=${TAR_NAME}"
  echo "ZIP_NAME=${ZIP_NAME}"
  echo "BASE_DIR=${BASE_DIR}"
} >> "$GITHUB_ENV"

{
  echo "version=${VERSION}"
  echo "discord_msg=Version ${VERSION} is available, download: [zip](https://downloads.phpvms.net/${ZIP_NAME}) | [tar](https://downloads.phpvms.net/${TAR_NAME})"
} >> "$GITHUB_OUTPUT"
