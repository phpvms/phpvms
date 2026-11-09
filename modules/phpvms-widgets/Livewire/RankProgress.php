<?php

declare(strict_types=1);

namespace Modules\PhpvmsWidgets\Livewire;

use App\Models\Rank;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Rank progress: the pilot's rank, the next rank by hours, and the percentage
 * of the way there.
 */
class RankProgress extends Component
{
    /**
     * Progress toward the next rank by hours; transfer hours count when the
     * `pilots.count_transfer_hours` setting is on. `to` is null at the top rank.
     *
     * @return array{from: string, to: ?string, pct: int}|null Null when the pilot has no rank.
     */
    public static function for(User $user): ?array
    {
        $rank = $user->rank;
        if ($rank === null) {
            return null;
        }

        $hours = ((int) ($user->flight_time ?? 0)) / 60.0;
        if (setting('pilots.count_transfer_hours', false)) {
            $hours += ((int) ($user->transfer_time ?? 0)) / 60.0;
        }

        $next = Rank::query()->where('hours', '>', $rank->hours)->orderBy('hours')->first();
        if ($next === null) {
            return ['from' => $rank->name, 'to' => null, 'pct' => 100];
        }

        $target = (float) $next->hours;

        return [
            'from' => $rank->name,
            'to'   => $next->name,
            'pct'  => (int) round(max(0.0, min(1.0, $hours / $target)) * 100),
        ];
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('phpvmswidgets::livewire.rank-progress', [
            'progress' => $user instanceof User ? self::for($user) : null,
        ]);
    }
}
