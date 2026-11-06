<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Support\Branding;
use Spatie\LaravelData\Data;

/**
 * Everything {@see Branding}'s accessors need, assembled once and cached.
 *
 * Holds raw values only: the `branding` slot's asset URLs (null when that asset
 * is not uploaded, or its disk is unreachable) and the two settings as stored.
 * The fallback chains — bundled assets, `config('app.name')`, the default brand
 * colour — stay in `Branding`, so the has* flags reflect an upload rather than
 * a fallback.
 */
final class BrandingData extends Data
{
    /**
     * @param array<int, string> $logoSizes URL of each logo derivative that resolves, keyed by size
     */
    public function __construct(
        public readonly string $siteName,
        public readonly string $brandColor,
        public readonly ?string $logo,
        public readonly ?string $logoDark,
        public readonly ?string $favicon,
        public readonly ?string $banner,
        public readonly array $logoSizes,
        public readonly bool $hasLogo,
        public readonly bool $hasDarkLogo,
        public readonly bool $hasFavicon,
    ) {}
}
