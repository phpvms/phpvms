#!/usr/bin/env bash

if test "$GIT_TAG_NAME"; then
  export VERSION=$NBGV_SemVer2

  php artisan phpvms:version --write --write-full-version "${VERSION}"
  export FULL_VERSION=$NBGV_SemVer2
else
  export BRANCH=${GITHUB_REF##*/}
  echo "On branch $BRANCH"

  # This now includes the pre-release version, so "-dev" by default
  export VERSION=$NBGV_SemVer2

  # Don't pass in a version here, just write out the latest hash
  php artisan phpvms:version --write "${VERSION}"
  export FULL_VERSION=$NBGV_SemVer2
fi

export FILE_NAME="phpvms-latest${NBGV_PrereleaseVersion}"
export TAR_NAME="$FILE_NAME.tar.gz"
export ZIP_NAME="$FILE_NAME.zip"
export BASE_DIR=`pwd`

# https://docs.github.com/en/actions/reference/workflow-commands-for-github-actions#environment-files
echo "BRANCH=${BRANCH}" >> "$GITHUB_ENV"
echo "FILE_NAME=${FILE_NAME}" >> "$GITHUB_ENV"
echo "TAR_NAME=${TAR_NAME}" >> "$GITHUB_ENV"
echo "ZIP_NAME=${ZIP_NAME}" >> "$GITHUB_ENV"
echo "BASE_DIR=${BASE_DIR}" >> "$GITHUB_ENV"
echo "FULL_VERSION=${FULL_VERSION}" >> "$GITHUB_ENV"
