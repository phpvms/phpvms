#!/usr/bin/env bash
echo "discord_msg=Version ${NBGV_SemVer2} is available, download: [zip](https://phpvms.cdn.vmslabs.net/$ZIP_NAME) | [tar](https://phpvms.cdn.vmslabs.net/$TAR_NAME)" >> "$GITHUB_OUTPUT"
