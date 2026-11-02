<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pireps\Pages;

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Actions\PirepFieldsAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use App\Support\Units\Time;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
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
     * Whether this request arrived without the calendar's deep-link params.
     *
     * Set in mount() and read back in bootedInteractsWithTable(), so it is only
     * ever true on the request that mounted the page. Deliberately not a
     * Livewire-visible property: a later filter interaction must be able to set
     * `departed` and keep it.
     */
    private bool $mountedWithoutDeepLink = false;

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

            return;
        }

        $this->mountedWithoutDeepLink = true;
    }

    /**
     * Drop a previously calendar-set `departed` filter on a direct visit, so the
     * list isn't stuck on an hour block for the rest of the session.
     *
     * This lives here rather than in mount() because the filters session key is
     * derived from the table (`InteractsWithTable::getTableSessionKey()` reads
     * `$this->getTable()`), and the table does not exist until the parent hook
     * below builds it — mount() runs first, so asking for the key there hits an
     * uninitialised property. The parent also hydrates the filters from the
     * session, which is why the stale block has to be cleared from both the
     * session and the hydrated filters afterwards, and the form refilled so the
     * current request doesn't render it.
     */
    #[Override]
    public function bootedInteractsWithTable(): void
    {
        parent::bootedInteractsWithTable();

        if (!$this->mountedWithoutDeepLink) {
            return;
        }

        $sessionKey = $this->getTableFiltersSessionKey();

        if (session()->has($sessionKey)) {
            $filters = session()->get($sessionKey, []);
            unset($filters['departed']);
            session()->put($sessionKey, $filters);
        }

        if (isset($this->tableFilters['departed'])) {
            unset($this->tableFilters['departed']);
            $this->getTableFiltersForm()->fill($this->tableFilters);
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

    /**
     * The tab strip is rendered by the custom list view above the table, so
     * the page content schema carries the tabs only; the view embeds the
     * table itself.
     */
    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getTabsContentComponent()]);
    }

    /**
     * Triage tabs (direction E): the review queue first, then the two
     * outcomes, then everything. The table already excludes drafts and
     * cancelled reports, so "All" is the same set the list shows untabbed.
     *
     * @return array<string, Tab>
     */
    #[Override]
    public function getTabs(): array
    {
        return [
            'needs_review' => Tab::make(__('pireps.needs_review'))
                ->badge(fn (): int => Pirep::where('state', PirepState::PENDING)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('state', PirepState::PENDING)),
            'accepted' => Tab::make(PirepState::ACCEPTED->getLabel())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('state', PirepState::ACCEPTED)),
            'rejected' => Tab::make(PirepState::REJECTED->getLabel())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('state', PirepState::REJECTED)),
            'all' => Tab::make(__('pireps.tab_all')),
        ];
    }

    /**
     * Land on "All", the same set the list showed before tabs existed. The
     * review queue is one click away; making it the default would silently
     * hide accepted reports from every direct visit and deep link.
     */
    #[Override]
    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }

    /**
     * A tab owns the state, so switching tabs clears the persisted `state`
     * filter; otherwise "Accepted" + a stale "Rejected" filter shows nothing.
     */
    #[Override]
    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        if (filled(data_get($this->tableFilters, 'state.value'))) {
            data_set($this->tableFilters, 'state.value', null);
            $this->getTableFiltersForm()->fill($this->tableFilters);
            $this->handleTableFilterUpdates();
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
            'total'    => Pirep::whereNotIn('state', [PirepState::DRAFT, PirepState::CANCELLED])->count(),
            'pending'  => Pirep::where('state', PirepState::PENDING)->count(),
            'accepted' => Pirep::where('state', PirepState::ACCEPTED)->count(),
        ]);
    }
}
