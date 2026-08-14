<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pireps\Pages;

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Actions\PirepFieldsAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use App\Support\Units\Time;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Override;
use Throwable;

class ListPireps extends ListRecords
{
    protected static string $resource = PirepResource::class;

    /**
     * Custom view: wraps the table and the hover-preview context panel in
     * the mockup's .with-context split (table + 350px sticky aside).
     */
    protected string $view = 'filament.pireps.pages.list-pireps';

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
     * Per-row payload for the hover preview panel, keyed by record key.
     * Only the current page's records are included, and the blade re-embeds
     * this on every Livewire render so pagination/filtering keep it fresh.
     * Rows omit empty fields; the panel renders whatever is present.
     *
     * @return array<string, array<string, string>>
     */
    public function getPreviewData(): array
    {
        return $this->getTableRecords()
            ->mapWithKeys(fn (Pirep $record): array => [
                (string) $record->getKey() => $this->previewRow($record),
            ])
            ->all();
    }

    /**
     * One cached preview row. The key rides on updated_at, so the payload is
     * built once while the report sits pending and regenerates on any state
     * change (accept/reject touch the row) — no invalidation hooks, old
     * entries just age out. Locale is in the key because the state and
     * flight-type labels are translated.
     *
     * @return array<string, string>
     */
    private function previewRow(Pirep $record): array
    {
        $key = sprintf(
            'pirep:preview:%s:%s:%s',
            $record->getKey(),
            $record->updated_at?->getTimestamp() ?? 0,
            app()->getLocale(),
        );

        return Cache::remember($key, now()->addDay(), function () use ($record): array {
            $filed = $record->submitted_at
                ? 'filed '.$record->submitted_at->format('j M H:i').'Z'
                : null;

            $aircraft = $record->aircraft
                ? trim($record->aircraft->registration.' · '.($record->aircraft->name ?? ''), ' ·')
                : null;

            $blockTime = $record->flight_time
                ? sprintf('%d:%02d', ...array_values(Time::minutesToTimeParts((int) $record->flight_time)))
                : null;

            $source = filled($record->source_name)
                ? $record->source?->getLabel().' · '.$record->source_name
                : $record->source?->getLabel();

            $chip = match ($record->state->getColor()) {
                'success' => 'chip--ok',
                'warning' => 'chip--warn',
                'danger'  => 'chip--bad',
                'info'    => 'chip--info',
                default   => 'chip--mute',
            };

            return array_filter([
                'ident'    => $record->ident,
                'sub'      => implode(' · ', array_filter([$record->user?->name, $filed])),
                'state'    => $record->state->getLabel(),
                'chip'     => $chip,
                'route'    => $record->dpt_airport_id.' → '.$record->arr_airport_id,
                'aircraft' => $aircraft,
                'block'    => $blockTime,
                'distance' => $record->distance
                    ? number_format((float) $record->distance->local()).' '.setting('units.distance')
                    : null,
                'landing' => $record->landing_rate !== null && (int) $record->landing_rate !== 0
                    ? number_format((float) $record->landing_rate).' fpm'
                    : null,
                'fuel' => $record->fuel_used
                    ? number_format((float) $record->fuel_used->local()).' '.setting('units.fuel')
                    : null,
                'source' => $source,
                'type'   => $record->flight_type->getLabel(),
                'url'    => PirepResource::getUrl('view', ['record' => $record]),
            ], filled(...));
        });
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
