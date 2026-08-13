<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry in the VA-wide activity feed (PvActivityFeed widget). A flat,
 * pre-rendered timeline row — the copy is built server-side so the widget stays
 * a dumb presenter.
 */
#[TypeScript]
final class ActivityEventData extends Data
{
    public function __construct(
        /** Stable composite id, e.g. "pirep:abc123" — also the :key. */
        public string $id,
        /** Discriminator: 'pirep' | 'pilot_joined' | 'flight_added' | 'award'. */
        public string $type,
        public string $title,
        public ?string $subtitle,
        /** ISO-8601 timestamp the feed sorts + renders "x ago" from. */
        public string $timestamp,
        /** lucide icon name for the timeline dot. */
        public string $icon,
    ) {}
}
