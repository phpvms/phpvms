<?php

declare(strict_types=1);

namespace App\Features\Dashboard\Livewire;

use App\Http\Controllers\Frontend\FeedController;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Throwable;

/**
 * The VA-wide activity widget: pilots flying now and the latest events.
 *
 * Loaded lazily, so the dashboard does not wait on the feed queries. It reads
 * the same data as `GET /api/activity` (FeedController), once per render.
 *
 * States: loading, error, empty, loaded.
 */
#[Lazy]
class ActivityFeed extends Component
{
    public function placeholder(): View
    {
        return view('livewire.dashboard.activity-feed', ['state' => 'loading', 'feed' => null]);
    }

    public function render(): View
    {
        try {
            $feed = app(FeedController::class)->index();
        } catch (Throwable $throwable) {
            report($throwable);

            return view('livewire.dashboard.activity-feed', ['state' => 'error', 'feed' => null]);
        }

        return view('livewire.dashboard.activity-feed', [
            'state' => $feed->events === [] ? 'empty' : 'loaded',
            'feed'  => $feed,
        ]);
    }

    /**
     * A compact relative time: "just now", "3h ago".
     */
    public static function timeAgo(string $iso, ?Carbon $now = null): string
    {
        $seconds = max(0, (int) Carbon::parse($iso)->diffInSeconds($now ?? Carbon::now(), true));

        if ($seconds < 60) {
            return 'just now';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes.'m ago';
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return $hours.'h ago';
        }

        $days = intdiv($hours, 24);
        if ($days < 30) {
            return $days.'d ago';
        }

        $months = intdiv($days, 30);

        return $months < 12 ? $months.'mo ago' : intdiv($months, 12).'y ago';
    }
}
