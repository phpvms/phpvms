<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AircraftStatus;
use App\Enums\PirepState;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\Subfleets\Resources\Aircraft\AircraftResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Aircraft;
use App\Models\Pirep;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Override;

/**
 * "Requires action" queue: pending PIREPs, pending user registrations and
 * aircraft under maintenance, oldest first, capped to 6 rows total.
 */
class RequiresActionWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.requires-action';

    protected static ?int $sort = 2;

    private const int LIMIT = 6;

    #[Override]
    protected function getViewData(): array
    {
        $rows = [];

        foreach (Pirep::query()->where('state', PirepState::PENDING)->with(['user:id,name', 'airline:id,icao,iata'])->oldest('submitted_at')->limit(self::LIMIT)->get() as $pirep) {
            $rows[] = [
                'flag'    => 'warn',
                'icon'    => 'tabler-clock',
                'strong'  => "{$pirep->ident} awaiting review",
                'sub'     => "{$pirep->user?->name} · {$pirep->dpt_airport_id} → {$pirep->arr_airport_id}",
                'when'    => $this->age($pirep->submitted_at),
                'sortKey' => $pirep->submitted_at,
                'url'     => PirepResource::getUrl('view', ['record' => $pirep]),
            ];
        }

        foreach (User::pending()->oldest('created_at')->limit(self::LIMIT)->get() as $user) {
            $rows[] = [
                'flag'    => 'info',
                'icon'    => 'tabler-user-plus',
                'strong'  => "{$user->name} awaiting approval",
                'sub'     => $user->email,
                'when'    => $this->age($user->created_at),
                'sortKey' => $user->created_at,
                'url'     => UserResource::getUrl('edit', ['record' => $user]),
            ];
        }

        foreach (Aircraft::query()->where('status', AircraftStatus::MAINTENANCE)->with('subfleet:id,name')->limit(self::LIMIT)->get() as $aircraft) {
            $rows[] = [
                'flag'    => 'maint',
                'icon'    => 'tabler-tool',
                'strong'  => "{$aircraft->registration} in maintenance",
                'sub'     => $aircraft->subfleet?->name,
                'when'    => '—',
                'sortKey' => $aircraft->updated_at,
                // AircraftResource is nested under Subfleets\Resources\Aircraft — its
                // edit route needs the parent subfleet id too.
                'url' => AircraftResource::getUrl('edit', ['subfleet' => $aircraft->subfleet_id, 'record' => $aircraft]),
            ];
        }

        usort($rows, fn (array $a, array $b): int => ($a['sortKey'] ?? now())->timestamp <=> ($b['sortKey'] ?? now())->timestamp);

        $rows = array_slice($rows, 0, self::LIMIT);

        return [
            'rows'  => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * Short age like "3d" / "2h" / "5m", matching the mockup's queue__when column.
     */
    private function age(?Carbon $timestamp): string
    {
        if ($timestamp === null) {
            return '—';
        }

        // diffInMinutes() returns a float; intdiv() needs an int under strict_types.
        $minutes = (int) $timestamp->diffInMinutes(now());

        return match (true) {
            $minutes >= 1440 => intdiv($minutes, 1440).'d',
            $minutes >= 60   => intdiv($minutes, 60).'h',
            default          => max($minutes, 1).'m',
        };
    }
}
