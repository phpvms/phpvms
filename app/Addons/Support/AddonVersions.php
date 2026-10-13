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
     * Fails closed whenever either side is not a clean numeric version — null,
     * empty, "dev-main", "999.invalid". Both have to parse for the comparison
     * to mean anything, and every caller reads "cannot compare" as "image wins".
     */
    public static function isNewer(?string $candidate, ?string $current): bool
    {
        if ($candidate === null || $current === null) {
            return false;
        }

        $candidateCore = self::normalise($candidate);
        $currentCore = self::normalise($current);

        if ($candidateCore === null || $currentCore === null) {
            return false;
        }

        try {
            return Version::fromString($candidateCore)
                ->isGreaterThan(Version::fromString($currentCore));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Normalise a version to `major.minor.patch`, or null when it is not a
     * version at all.
     *
     * Accepts a leading "v", a partial version ("1.2" becomes "1.2.0") and a
     * build/prerelease suffix, which is stripped — so 2.0.0-rc1 and 2.0.0
     * compare equal and the image wins the tie.
     *
     * Rejects anything whose components are not purely digits, rather than
     * casting them. A naive (int) cast turns "999.invalid" into "999.0.0",
     * which would then supersede a real 1.0.0: junk must never win a
     * comparison that decides which code loads.
     */
    private static function normalise(string $version): ?string
    {
        $core = preg_split('/[-+]/', ltrim(trim($version), 'vV'))[0] ?? '';

        if ($core === '') {
            return null;
        }

        $parts = explode('.', $core);

        if (count($parts) > 3) {
            return null;
        }

        foreach ($parts as $part) {
            if (preg_match('/^\d+$/', $part) !== 1) {
                return null;
            }
        }

        return implode('.', array_map(
            static fn (string $part): int => (int) $part,
            array_pad($parts, 3, '0'),
        ));
    }
}
