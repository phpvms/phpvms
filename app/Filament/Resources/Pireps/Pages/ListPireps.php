<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pireps\Pages;

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Actions\PirepFieldsAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Override;
use Throwable;

class ListPireps extends ListRecords
{
    protected static string $resource = PirepResource::class;

    /**
     * The dashboard activity calendar deep-links here with
     * ?departed_from=...&departed_to=... (one-hour block). Hydrate the
     * `departed` table filter before the table boots so the list is
     * pre-filtered to that time block.
     */
    #[Override]
    public function mount(): void
    {
        parent::mount();

        $from = request()->query('departed_from');
        $to = request()->query('departed_to');

        if (filled($from) || filled($to)) {
            // Deep-linked from the dashboard calendar: pre-filter to that block.
            // Parse defensively — a malformed query value must not 500 the list.
            $this->tableFilters['departed'] = [
                'from' => filled($from) ? $this->parseDateQuery($from) : null,
                'to'   => filled($to) ? $this->parseDateQuery($to) : null,
            ];
        } elseif (session()->has($this->getTableFiltersSessionKey())) {
            // Direct visit (no deep-link params): drop a previously
            // calendar-set `departed` filter so the list isn't stuck on an
            // hour block for the rest of the session — and from the hydrated
            // filters so the current request doesn't render the stale block.
            $filters = session()->get($this->getTableFiltersSessionKey(), []);
            unset($filters['departed']);
            session()->put($this->getTableFiltersSessionKey(), $filters);

            unset($this->tableFilters['departed']);
        }
    }

    /**
     * Parse a query-string date without letting a malformed value throw.
     */
    private function parseDateQuery(string $value): ?string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            PirepFieldsAction::make(),
        ];
    }

    /**
     * Inline metrics row (band header eyebrow variant) replacing the old
     * StatsOverviewWidget cards. `$total` mirrors the table's base query
     * scope (see PirepsTable::configure) so the count matches what's
     * actually listed.
     */
    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        return view('filament.pireps.partials.head-metrics', [
            'total'    => Pirep::whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED])->count(),
            'pending'  => Pirep::where('state', PirepState::PENDING)->count(),
            'accepted' => Pirep::where('state', PirepState::ACCEPTED)->count(),
        ]);
    }
}
