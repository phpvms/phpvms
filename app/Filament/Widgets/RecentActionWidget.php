<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AircraftStatus;
use App\Enums\PirepState;
use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\Subfleets\Resources\Aircraft\AircraftResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Aircraft;
use App\Models\Pirep;
use App\Models\User;
use Carbon\Carbon;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;
use Override;

/**
 * Pending PIREPs, user registrations, and aircraft maintenance, oldest first.
 */
class RecentActionWidget extends TableWidget implements DynamicWidget
{
    use IsDynamicDashboardWidget;

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = null;

    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.recent_action');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 4;
    }

    public static function getDynamicDashboardDefaultHeight(): int
    {
        return 6;
    }

    private const int LIMIT = 6;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            // ->heading(__('filament.dashboard.recent_action'))
            ->records(fn (): array => $this->records())
            ->columns([
                TextColumn::make('strong')
                    ->label(__('filament.dashboard.recent_action'))
                    ->description(fn (array $record): ?string => $record['sub'])
                    ->icon(fn (array $record): TablerIcon => $record['icon'])
                    ->iconColor(fn (array $record): string => $record['color'])
                    ->wrap(),
                TextColumn::make('when')
                    ->label(__('common.updated'))
                    ->alignEnd(),
            ])
            ->recordUrl(fn (array $record): string => $record['url'])
            ->paginated(false)
            ->emptyStateHeading(__('filament.dashboard.nothing_needs_attention'))
            ->emptyStateIcon(TablerIcon::CircleCheck);
    }

    /**
     * @return array<string, array{
     *     color: string,
     *     icon: TablerIcon,
     *     strong: string,
     *     sub: ?string,
     *     when: string,
     *     sortKey: ?Carbon,
     *     url: string
     * }>
     */
    private function records(): array
    {
        $records = [];

        foreach (Pirep::query()->where('state', PirepState::PENDING)->with(['user:id,name', 'airline:id,icao,iata'])->oldest('submitted_at')->limit(self::LIMIT)->get() as $pirep) {
            $records["pirep-{$pirep->id}"] = [
                'color'   => 'warning',
                'icon'    => TablerIcon::Clock,
                'strong'  => __('filament.dashboard.pirep_awaiting_review', ['ident' => $pirep->ident]),
                'sub'     => "{$pirep->user?->name} · {$pirep->dpt_airport_id} → {$pirep->arr_airport_id}",
                'when'    => $this->age($pirep->submitted_at),
                'sortKey' => $pirep->submitted_at,
                'url'     => PirepResource::getUrl('view', ['record' => $pirep]),
            ];
        }

        foreach (User::pending()->oldest('created_at')->limit(self::LIMIT)->get() as $user) {
            $records["user-{$user->id}"] = [
                'color'   => 'info',
                'icon'    => TablerIcon::UserPlus,
                'strong'  => __('filament.dashboard.user_awaiting_approval', ['name' => $user->name]),
                'sub'     => $user->email,
                'when'    => $this->age($user->created_at),
                'sortKey' => $user->created_at,
                'url'     => UserResource::getUrl('edit', ['record' => $user]),
            ];
        }

        foreach (Aircraft::query()->where('status', AircraftStatus::MAINTENANCE)->with('subfleet:id,name')->limit(self::LIMIT)->get() as $aircraft) {
            $records["aircraft-{$aircraft->id}"] = [
                'color'   => 'warning',
                'icon'    => TablerIcon::Tool,
                'strong'  => __('filament.dashboard.aircraft_in_maintenance', ['registration' => $aircraft->registration]),
                'sub'     => $aircraft->subfleet?->name,
                'when'    => '—',
                'sortKey' => $aircraft->updated_at,
                'url'     => AircraftResource::getUrl('edit', ['subfleet' => $aircraft->subfleet_id, 'record' => $aircraft]),
            ];
        }

        uasort($records, fn (array $a, array $b): int => ($a['sortKey'] ?? now())->timestamp <=> ($b['sortKey'] ?? now())->timestamp);

        return array_slice($records, 0, self::LIMIT, preserve_keys: true);
    }

    private function age(?Carbon $timestamp): string
    {
        if (!$timestamp instanceof Carbon) {
            return '—';
        }

        $minutes = (int) $timestamp->diffInMinutes(now());

        return match (true) {
            $minutes >= 1440 => intdiv($minutes, 1440).'d',
            $minutes >= 60   => intdiv($minutes, 60).'h',
            default          => max($minutes, 1).'m',
        };
    }
}
