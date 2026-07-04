<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload for the VA-wide activity feed widget: a live "pilots flying now" count
 * plus the most recent activity across the whole VA (PIREPs, joins, new flights,
 * awards), newest first. Served by FeedController@index at GET /api/activity.
 */
#[TypeScript]
final class ActivityFeedData extends Data
{
    /**
     * @param list<ActivityEventData> $events
     */
    public function __construct(
        public int $flyingNow,
        public array $events,
    ) {}
}
