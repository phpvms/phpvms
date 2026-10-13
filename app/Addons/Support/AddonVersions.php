<?php

declare(strict_types=1);

namespace App\Addons\Support;

use Throwable;
use Version\Version;

/**
 * Semver comparison for deciding which copy of an addon loads when the same
 * addon is present in both roots.
 *
 * Core cannot use the addon manager's `CompatibilityEvaluator`, which does the
 * same job for the catalog UI: design decision 1 requires core to install and
 * update modules — including the manager — even when the manager is broken, so
 * core must not depend on module classes. `nikolaposa/version` is a root
 * composer dependency, so the library itself is fair game.
 *
 * Stateless and Octane-safe.
 */
final class AddonVersions
{
    /**
     * Whether the copy installed at runtime should win over the image's copy.
     *
     * Deliberately asymmetric, and the asymmetry is the whole safety property.
     * The runtime copy wins ONLY when both versions parse and it is strictly
     * newer. Every other case — either side unversioned, unparseable, or equal
     * — hands it to the image.
     *
     * Failing toward the image is what stops this reintroducing the staleness
     * bug the two roots exist to fix. If an unversioned runtime copy could win,
     * it would shadow the image's copy permanently and no later deploy could
     * ever take that back; the cost of the other direction is merely that a
     * bundled module which declares no version is not registry-updatable, which
     * is visible, logged, and fixed by declaring one.
     */
    public static function installedSupersedesImage(?string $installed, ?string $image): bool
    {
        return self::isNewer($installed, $image);
    }

    /**
     * True when $candidate is a strictly newer semver than $current.
     *
     * Fails closed when $current carries no parseable version — empty, null, or
     * non-numeric like "dev-main", which pad() would otherwise coerce to 0.0.0
     * and report every candidate as newer. A malformed $candidate is harmless:
     * it pads to 0.0.0 and compares as not-newer.
     */
    public static function isNewer(?string $candidate, ?string $current): bool
    {
        if ($candidate === null || $current === null) {
            return false;
        }

        if ($current === '' || !preg_match('/\d/', $current)) {
            return false;
        }

        try {
            return Version::fromString(self::pad($candidate))
                ->isGreaterThan(Version::fromString(self::pad($current)));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Pad a partial version ("1.2") to a full semver ("1.2.0") and strip any
     * build/prerelease suffix so nikolaposa/version accepts it.
     */
    private static function pad(string $version): string
    {
        $core = preg_split('/[-+]/', ltrim(trim($version), 'vV'))[0] ?? '';
        $parts = array_slice(array_pad(explode('.', $core), 3, '0'), 0, 3);

        return implode('.', array_map(fn ($part): int => (int) $part, $parts));
    }
}
