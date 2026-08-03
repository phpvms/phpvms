<?php

declare(strict_types=1);

namespace Modules\AddonManager\Services;

use App\Services\VersionService;
use Throwable;
use Version\Version;

/**
 * Evaluates a catalog entry's minimum PHP / phpVMS constraints against the
 * running versions. Comparison is semver-aware (string compare mis-orders 2.10
 * vs 2.9), padding partial constraints like "8.4" to "8.4.0".
 */
class CompatibilityEvaluator
{
    public function __construct(private readonly VersionService $versions) {}

    /**
     * @param  array<string, mixed>                     $entry a normalized RegistryClient entry
     * @return array{compatible: bool, reason: ?string}
     */
    public function evaluate(array $entry): array
    {
        $reasons = [];

        $minPhp = (string) ($entry['min_php'] ?? '');
        if ($minPhp !== '' && !$this->satisfies(PHP_VERSION, $minPhp)) {
            $reasons[] = sprintf('php ≥ %s — you run %s', $minPhp, $this->short(PHP_VERSION));
        }

        $minPhpvms = (string) ($entry['min_phpvms'] ?? '');
        $running = $this->versions->getCurrentVersion(false);
        if ($minPhpvms !== '' && !$this->satisfies($running, $minPhpvms)) {
            $reasons[] = sprintf('phpvms ≥ %s — you run %s', $minPhpvms, $this->short($running));
        }

        return [
            'compatible' => $reasons === [],
            'reason'     => $reasons === [] ? null : 'requires '.implode(', ', $reasons),
        ];
    }

    /**
     * True when $candidate is a strictly newer semver than $current. Fails
     * closed (no false "newer") when $current carries no parseable version —
     * empty, or non-numeric like "dev-main" (which pad() would otherwise coerce
     * to 0.0.0 and report every catalog version as an update). A malformed
     * $candidate is harmless: it pads to 0.0.0 and compares as not-newer.
     */
    public function isNewer(string $candidate, string $current): bool
    {
        if ($current === '' || !preg_match('/\d/', $current)) {
            return false;
        }

        try {
            return Version::fromString($this->pad($candidate))
                ->isGreaterThan(Version::fromString($this->pad($current)));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * True when $running is at least $min. On any parse failure it fails open
     * (treats as satisfied) rather than blocking install on a malformed
     * constraint the operator can't fix.
     */
    private function satisfies(string $running, string $min): bool
    {
        try {
            $runningVersion = Version::fromString($this->pad($running));
            $minVersion = Version::fromString($this->pad($min));

            return !$runningVersion->isLessThan($minVersion);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Strip a leading constraint operator or `v` from a loose version/min string
     * (">=8.4", "^8.4", "v8.4" → "8.4"). The single home for version-string
     * normalization so RegistryClient doesn't hand-roll its own — this class owns
     * version handling (comparison runs through nikolaposa/version).
     */
    public static function normalizeMin(string $version): string
    {
        return preg_replace('/^[^\d]*/', '', trim($version)) ?? '';
    }

    /**
     * Pad a partial version ("8.4") to a full semver ("8.4.0") and strip any
     * build/prerelease suffix so nikolaposa/version accepts it.
     */
    private function pad(string $version): string
    {
        $core = preg_split('/[-+]/', self::normalizeMin($version))[0] ?? '';
        $parts = array_slice(array_pad(explode('.', $core), 3, '0'), 0, 3);

        return implode('.', array_map(fn ($p): int => (int) $p, $parts));
    }

    private function short(string $version): string
    {
        $parts = explode('.', $this->pad($version));

        return $parts[0].'.'.$parts[1];
    }
}
