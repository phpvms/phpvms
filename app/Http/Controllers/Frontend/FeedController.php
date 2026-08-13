<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Enums\PirepState;
use App\Http\Data\ActivityEventData;
use App\Http\Data\ActivityFeedData;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;
use App\Models\UserAward;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * JSON endpoint for the VA-wide activity feed widget (PvActivityFeed).
 *
 * GET /api/activity
 *
 * Returns an {@see ActivityFeedData}: a live "pilots flying now" count plus the
 * most recent activity across the whole VA (accepted PIREPs, new pilots, new
 * flights, awards), merged newest-first and capped. The widget fetches this once
 * on mount, so the query never blocks the Inertia page's first paint.
 */
final class FeedController extends Controller
{
    /** Rows pulled per source, and the size of the final merged feed. */
    private const int LIMIT = 15;

    public function index(): ActivityFeedData
    {
        return new ActivityFeedData(
            flyingNow: $this->flyingNow(),
            events: $this->events(),
        );
    }

    /** Distinct pilots with a live flight right now (in progress or paused). */
    private function flyingNow(): int
    {
        return Pirep::whereIn('state', [PirepState::IN_PROGRESS, PirepState::PAUSED])
            ->distinct()
            ->count('user_id');
    }

    /**
     * Merge every event source, sort newest-first, cap to LIMIT.
     *
     * @return list<ActivityEventData>
     */
    private function events(): array
    {
        return $this->pireps()
            ->concat($this->pilots())
            ->concat($this->flights())
            ->concat($this->awards())
            ->sortByDesc(fn (array $e): int => $e['ts']->getTimestamp())
            ->take(self::LIMIT)
            ->map(fn (array $e): ActivityEventData => $e['event'])
            ->values()
            ->all();
    }

    /**
     * Recently accepted PIREPs → "{pilot} filed {ident}".
     *
     * @return Collection<int, array{ts: Carbon, event: ActivityEventData}>
     */
    private function pireps(): Collection
    {
        return Pirep::with(['user', 'airline', 'dpt_airport', 'arr_airport'])
            ->where('state', PirepState::ACCEPTED)
            ->orderByDesc('submitted_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (Pirep $p): array {
                $ts = Carbon::make($p->submitted_at) ?? $p->created_at ?? Carbon::now();

                return [
                    'ts'    => $ts,
                    'event' => new ActivityEventData(
                        id: 'pirep:'.$p->id,
                        type: 'pirep',
                        title: ($p->user->name ?? 'A pilot').' filed '.$p->ident,
                        subtitle: $this->route($p->dpt_airport?->icao, $p->arr_airport?->icao),
                        timestamp: $ts->toIso8601String(),
                        icon: 'plane-arrival',
                    ),
                ];
            });
    }

    /**
     * Recently registered pilots → "{name} joined".
     *
     * @return Collection<int, array{ts: Carbon, event: ActivityEventData}>
     */
    private function pilots(): Collection
    {
        return User::orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (User $u): array {
                $ts = $u->created_at ?? Carbon::now();

                return [
                    'ts'    => $ts,
                    'event' => new ActivityEventData(
                        id: 'pilot:'.$u->id,
                        type: 'pilot_joined',
                        title: $u->name.' joined',
                        subtitle: null,
                        timestamp: $ts->toIso8601String(),
                        icon: 'user-plus',
                    ),
                ];
            });
    }

    /**
     * Recently added flights → "New flight {ident}".
     *
     * @return Collection<int, array{ts: Carbon, event: ActivityEventData}>
     */
    private function flights(): Collection
    {
        return Flight::with(['airline', 'dpt_airport', 'arr_airport'])
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (Flight $f): array {
                $ts = $f->created_at ?? Carbon::now();

                return [
                    'ts'    => $ts,
                    'event' => new ActivityEventData(
                        id: 'flight:'.$f->id,
                        type: 'flight_added',
                        title: 'New flight '.$f->ident,
                        subtitle: $this->route($f->dpt_airport?->icao, $f->arr_airport?->icao),
                        timestamp: $ts->toIso8601String(),
                        icon: 'route',
                    ),
                ];
            });
    }

    /**
     * Recently earned awards → "{pilot} earned {award}".
     *
     * @return Collection<int, array{ts: Carbon, event: ActivityEventData}>
     */
    private function awards(): Collection
    {
        return UserAward::with(['user', 'award'])
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (UserAward $ua): array {
                $ts = $ua->created_at ?? Carbon::now();

                return [
                    'ts'    => $ts,
                    'event' => new ActivityEventData(
                        id: 'award:'.$ua->id,
                        type: 'award',
                        title: ($ua->user->name ?? 'A pilot').' earned '.($ua->award->name ?? 'an award'),
                        subtitle: null,
                        timestamp: $ts->toIso8601String(),
                        icon: 'award',
                    ),
                ];
            });
    }

    /** "KJFK → EGLL", or null when neither endpoint is known. */
    private function route(?string $dpt, ?string $arr): ?string
    {
        if ($dpt === null && $arr === null) {
            return null;
        }

        return ($dpt ?? '—').' → '.($arr ?? '—');
    }
}
