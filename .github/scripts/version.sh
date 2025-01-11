#!/usr/bin/env bash

#
# Reference:
# https://gitversion.net/docs/reference/variables
# https://github.com/GitTools/actions/blob/main/docs/examples/github/gitversion/execute.md
#
export VERSION=${GitVersion_FullSemVer}
export FULL_VERSION=${VERSION}
php artisan phpvms:version --write --write-full-version "${VERSION}"

if test "$GIT_TAG_NAME"; then
  echo "Tagged with ${GIT_TAG_NAME}"
  export FILE_NAME="phpvms-${GIT_TAG_NAME}"
else
  export BRANCH=${GITHUB_REF##*/}
  echo "On branch $BRANCH"

  export FILE_NAME="phpvms-${GitVersion_PreReleaseLabel}"
fi

export TAR_NAME="$FILE_NAME.tar.gz"
export ZIP_NAME="$FILE_NAME.zip"
export BASE_DIR=`pwd`

echo "BRANCH=${BRANCH}"
echo "FILE_NAME=${FILE_NAME}"
echo "TAR_NAME=${TAR_NAME}"
echo "ZIP_NAME=${ZIP_NAME}"
echo "BASE_DIR=${BASE_DIR}"
echo "FULL_VERSION=${FULL_VERSION}"

# https://docs.github.com/en/actions/reference/workflow-commands-for-github-actions#environment-files
echo "BRANCH=${BRANCH}" >> "$GITHUB_ENV"
echo "FILE_NAME=${FILE_NAME}" >> "$GITHUB_ENV"
echo "TAR_NAME=${TAR_NAME}" >> "$GITHUB_ENV"
echo "ZIP_NAME=${ZIP_NAME}" >> "$GITHUB_ENV"
echo "BASE_DIR=${BASE_DIR}" >> "$GITHUB_ENV"
echo "FULL_VERSION=${FULL_VERSION}" >> "$GITHUB_ENV"

echo "discord_msg=Version ${FULL_VERSION} is available, download: [zip](https://phpvms.cdn.vmslabs.net/$ZIP_NAME) | [tar](https://phpvms.cdn.vmslabs.net/$TAR_NAME)" >> "$GITHUB_OUTPUT"
