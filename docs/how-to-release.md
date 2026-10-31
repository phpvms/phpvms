# How to Release

A release is a published GitHub release. Publishing one triggers
`.github/workflows/release.yml`, which builds the package at the release's
target commit, stamps the tag into the shipped `composer.json`, attaches the
zip and tarball to the release, uploads them to bunny storage
(`https://downloads.phpvms.net/<file>`), and posts to Discord. A second job
builds `Dockerfile.prod` and pushes `ghcr.io/phpvms/phpvms:<tag>`. Stable
releases also get `:<major>.<minor>`; prereleases get only `:<tag>`. `:latest`
moves only when the release is not marked as a prerelease.

There is no release tooling beyond `gh` and that workflow. Nothing is committed
back to the repo by a release.

## Versions

- The version an install reports comes from the `version` field in
  `composer.json`. `VersionService::getCurrentVersion()` reads it.
- The committed value is a placeholder for git checkouts, e.g. `8.0.0-dev`.
  Bump it by hand when `main` moves to the next minor (`8.1.0-dev`).
- CI overwrites it with the release tag on the build runner only
  (`composer config version <tag>` in `.github/scripts/version.sh`), before
  `composer install`, and refreshes the lock hash so the shipped
  `composer.json`, `composer.lock` and `vendor/composer/installed.php` agree.
- Tags are plain semver with no `v` prefix: `8.0.0-alpha.1`, `8.0.0`, `8.0.1`.
- Installs check for updates against the GitHub releases API and honour the
  release's prerelease flag against the `general.check_prerelease_version`
  setting. The prerelease checkbox is the only switch.

## Branches

- `main` is the next minor. Prereleases are cut from it.
- Each minor gets a `releases/<major>.<minor>` branch once it ships. Stable
  and patch releases are cut from that branch, and fixes are backported to
  it with PRs.

## Cut a prerelease from main

```bash
gh release create 8.0.0-alpha.1 --target main --prerelease --generate-notes
```

## Cut the first stable release of a minor

```bash
git switch main && git pull
git switch -c releases/8.0 && git push -u origin releases/8.0
gh release create 8.0.0 --target releases/8.0 --generate-notes
```

Then bump the placeholder on `main` to `8.1.0-dev` in `composer.json`, refresh
the lock hash with `composer update --lock --no-install`, and open a PR.

## Cut a patch release

```bash
gh release create 8.0.1 --target releases/8.0 --generate-notes
```

## Checking a release

- The workflow run is listed under Actions → release, named after the tag.
- The GitHub release should have four assets: `phpvms-<tag>.zip`,
  `phpvms-<tag>.tar.gz`, and a `.sha256` for each.
- The same files should be reachable at `https://downloads.phpvms.net/<file>`.
- `ghcr.io/phpvms/phpvms:<tag>` should exist under the repo's packages.

## Secrets the workflow needs

- `BUNNY_SECRET_ACCESS_KEY` — bunny storage zone password.
- `V8_DISCORD_WEBHOOK` — Discord webhook for release announcements.
- `GITHUB_TOKEN` is provided automatically and is enough to attach assets.
