<?php

declare(strict_types=1);

namespace App\Features\Assets\Enums;

/**
 * Who consumes an asset — the PRIMARY filter when listing, because every real
 * query is "give me everything this consumer needs". A splash screen wants the
 * branding slot, not "every image", which would also drag in gauge textures and
 * paintkits.
 *
 * Distinct from the `source` column, which records who STORED the asset. A
 * module may write into a shared slot; the slot still describes the consumer.
 *
 * Closed on purpose and validated on write: a slot becomes a URL segment and an
 * on-disk directory name downstream, so a free-form value is a path-traversal
 * vector.
 */
enum AssetSlot: string
{
    /** Site branding — logos, banners, favicons, the application icon. */
    case BRANDING = 'branding';

    /** Airline marks, keyed by lowercased ICAO. */
    case AIRLINE_LOGO = 'airline-logo';

    /** Uploaded audio a client can play. */
    case SOUNDS = 'sounds';

    /** A gauge's own files: component source and anything it references. */
    case GAUGE = 'gauge';

    /** Aircraft liveries applied to a 3D model. */
    case PAINTKIT = 'paintkit';
}
