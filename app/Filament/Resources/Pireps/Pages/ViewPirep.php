<?php

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use App\Models\PirepEvent;
use App\Services\Finance\PirepFinanceService;
use App\Services\GeoService;
use App\Services\Pirep\PerformanceChartService;
use App\Support\PirepView\PirepViewTabRegistry;
use Closure;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Override;
use Throwable;

/**
 * @property Pirep $record
 */
class ViewPirep extends ViewRecord
{
    protected static string $resource = PirepResource::class;

    /**
     * GeoJSON feature collections for the route map, serialized to plain arrays
     * so Livewire can hydrate them between requests. GeoService returns
     * \GeoJson\Feature\FeatureCollection value objects which Livewire cannot
     * serialize; we convert to associative arrays in mount().
     *
     * Shape: ['planned_rte_points' => [...], 'planned_rte_line' => [...],
     *         'actual_route_points' => [...], 'actual_route_line' => [...]]
     *
     * @var array<string, mixed>
     */
    public array $mapFeatures = [];

    /**
     * Chart.js payload for the Performance card. Null when the PIREP has no
     * ACARS samples — blade switches to the empty stub.
     *
     * @var array<string, mixed>|null
     */
    public ?array $performance = null;

    /**
     * Sort direction for the Flight Log timeline. Toggled between 'asc'
     * (earliest first) and 'desc' (latest first) via wire:click.
     */
    public string $logSort = 'asc';

    /**
     * Custom blade view that renders the PIREP detail layout.
     * The page extends ViewRecord so Filament resolves the record from the
     * URL and applies policy checks; we just opt out of the default infolist
     * rendering and provide our own markup.
     */
    protected string $view = 'filament.pireps.pages.view-pirep';

    #[Override]
    public function getHeading(): string
    {
        $record = $this->record;
        $parts = [$record->ident];
        if ($record->aircraft) {
            $parts[] = $record->aircraft->registration;
        }

        $parts[] = $record->dpt_airport_id.'→'.$record->arr_airport_id;

        return implode(' ', $parts);
    }

    /**
     * Plain-text subline under the heading (mockup pirep.html:476):
     * "{pilot name} · filed {date} via {source}". Mirrors the source-label
     * logic the old avatar-hero header partial used to compute.
     */
    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->record;

        $filed = $record->submitted_at
            ? 'filed '.$record->submitted_at->format('j M H:i').'Z'
            : null;

        $sourceLabel = filled($record->source_name)
            ? $record->source?->getLabel().' · '.$record->source_name
            : $record->source?->getLabel();

        if (filled($sourceLabel)) {
            $filed = filled($filed) ? $filed.' via '.$sourceLabel : 'via '.$sourceLabel;
        }

        $parts = array_filter([$record->user?->name, $filed], filled(...));

        /* State chip rides the subline (mockup puts it beside the actions) so
         * the report's state is visible without opening a tab. */
        $chipClass = match ($record->state->getColor()) {
            'success' => 'chip--ok',
            'warning' => 'chip--warn',
            'danger'  => 'chip--bad',
            'info'    => 'chip--info',
            default   => 'chip--mute',
        };
        $chip = '<span class="chip '.$chipClass.'">'.e($record->state->getLabel()).'</span>';

        return new HtmlString(
            $chip.($parts === [] ? '' : '<span>'.e(implode(' · ', $parts)).'</span>'),
        );
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        // No default infolist — the custom blade renders the detail layout.
        return $schema->components([]);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        return [
            ...parent::getViewData(),
            'extensionTabs' => $this->extensionTabs(),
        ];
    }

    /**
     * Addon-registered subtabs for this record (PirepViewTabRegistry): drop the
     * ones whose `visible` closure says no, resolve label/badge, render each
     * panel to HTML.
     *
     * Panels are rendered HERE — from getViewData(), before the page view
     * starts rendering — and never from inside the blade. Laravel's
     * View::render() calls Factory::flushState() when a view throws, which
     * wipes the GLOBAL component/section stacks; a throwing addon view rendered
     * inside the page would take the surrounding <x-filament-panels::page>
     * component down with it (unwinding the output buffers is not enough).
     * Rendering to a string first keeps a failure inside its own panel.
     *
     * The addon view is passed ONLY the record, never the page's scope.
     *
     * Two independent failure modes, both contained: a throwing `visible`,
     * `label` or `badge` closure drops the whole tab (with no label there is
     * nothing to put on a button), while a throwing view keeps the tab and
     * shows fallback content in its panel. Both report the exception.
     *
     * `domId` is prefixed and hash-suffixed so it can neither collide with the
     * built-in `tab-*`/`t-*` ids nor with another addon whose id slugifies the
     * same way (`acme.a.b` and `acme.a-b` both slugify to `acme-a-b`).
     *
     * @return array<int, array{id: string, domId: string, label: string, badge: string|int|null, html: string}>
     */
    private function extensionTabs(): array
    {
        $record = $this->record;
        $resolve = static fn (mixed $value): mixed => $value instanceof Closure ? $value($record) : $value;

        $tabs = [];

        foreach (app(PirepViewTabRegistry::class)->ordered() as $tab) {
            try {
                if (!$resolve($tab['visible'] ?? true)) {
                    continue;
                }

                $label = (string) $resolve($tab['label']);
                $badge = $resolve($tab['badge'] ?? null);
            } catch (Throwable $throwable) {
                report($throwable);

                continue;
            }

            try {
                $html = view($tab['view'], ['record' => $record])->render();
            } catch (Throwable $throwable) {
                report($throwable);
                $html = '<div class="panel__body panel__body--centred"><p class="text-ink-3 text-sm">'
                    .e(__('filament.extension_tab_error'))
                    .'</p></div>';
            }

            $tabs[] = [
                'id'    => $tab['id'],
                'domId' => 'ext-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $tab['id']).'-'.substr(md5($tab['id']), 0, 6),
                'label' => $label,
                'badge' => $badge,
                'html'  => $html,
            ];
        }

        return $tabs;
    }

    /**
     * Recalculate finances for this PIREP and refresh the page.
     */
    public function recalculateFinances(): void
    {
        app(PirepFinanceService::class)->processFinancesForPirep($this->record);

        Notification::make()
            ->success()
            ->title(__('filament.finances_recalculated'))
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Computed getter for the Flight Log entries. Queries pirep_events rows
     * with a non-null log string, ordered by the current $logSort direction.
     *
     * @return Collection<int, PirepEvent>
     */
    public function getLogEntriesProperty(): Collection
    {
        return PirepEvent::query()
            ->where('pirep_id', $this->record->id)
            ->whereNotNull('log')
            ->orderBy('created_at', $this->logSort)
            ->get();
    }

    /**
     * Toggle the Flight Log sort direction between ascending and descending.
     */
    public function toggleLogSort(): void
    {
        $this->logSort = $this->logSort === 'asc' ? 'desc' : 'asc';
    }

    /**
     * Skip ViewRecord's default form/infolist fill. The page renders a custom
     * blade that reads $record directly, so we don't need (or want) Filament
     * to hydrate a form schema from the model attributes. Pirep has custom
     * value-object casts (Fuel, Distance) which break NumberStateCast.
     */
    #[Override]
    protected function fillForm(): void
    {
        // no-op
    }

    /**
     * Order is deliberate: destructive first (left-most, away from the
     * primary flow), then Edit, then the state decision as a dropdown
     * button on the right (Tailwind Plus dropdown pattern).
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->icon(Phosphor::TrashLight)
                ->icon(Phosphor::TrashLight),
            ForceDeleteAction::make()->icon(Phosphor::TrashSimpleLight),
            RestoreAction::make()->icon(Phosphor::ArrowUUpLeftLight),
            /* Default EditAction color is primary, which takes the hero's
             * inverted-ink fill — too loud next to Delete/Status. Neutral
             * field, intent in the icon tint, like its neighbours. */
            EditAction::make()
                ->icon(Phosphor::PencilSimpleLight)
                ->color('info'),
            ActionGroup::make([
                AcceptAction::make(),
                RejectAction::make(),
            ])
                ->label(__('common.status'))
                ->icon(Phosphor::CaretDownLight)
                ->iconPosition(IconPosition::After)
                ->button()
                ->color('gray'),
        ];
    }

    #[Override]
    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Eager-load everything the detail blade and embedded relation managers
        // read. Lazy loading is disabled in non-production environments
        // (Model::preventLazyLoading in AppServiceProvider), so any nested
        // relation access from a blade column or RM closure must be preloaded
        // here or the request hard-fails.
        //
        // - 'user.rank' covers sidebar's $pilot->rank->name access.
        // - 'comments.user' covers CommentsRelationManager's user.name column.
        // - 'fares.fare' covers FaresRelationManager's fare column (PirepFare->fare).
        // - 'fares.pirep' + 'field_values.pirep' cover the `$record->pirep->read_only`
        //   guard inside the FaresRelationManager and FieldValuesRelationManager
        //   column `disabled` closures (fires when a user attempts to edit a row).
        // - 'transactions' covers TransactionsRelationManager listing.
        // - 'field_values' feeds the `fields` Attribute accessor used by the sidebar.
        // - 'fields' itself is an Attribute, not a relation — don't load it.
        $this->record->loadMissing([
            'user.rank',
            'aircraft',
            'dpt_airport',
            'arr_airport',
            'comments.user',
            'transactions',
            'fares.fare',
            'fares.pirep',
            'field_values.pirep',
            'field_values',
            'metadata',
        ]);

        // GeoService returns FeatureCollection value objects; convert to plain
        // arrays so Livewire can serialize the property between requests.
        //
        // A malformed ACARS sample (non-numeric lat/lon, missing airport
        // relation) should not 500 the entire view — log + render without
        // the map. The blade's $hasRouteMap guard hides the map when
        // mapFeatures stays empty.
        try {
            $features = app(GeoService::class)->pirepGeoJson($this->record);
            $this->mapFeatures = json_decode((string) json_encode($features), true) ?? [];
        } catch (Throwable $throwable) {
            Log::warning('PIREP map build failed', [
                'pirep_id' => $this->record->id,
                'error'    => $throwable->getMessage(),
            ]);
            $this->mapFeatures = [];
        }

        // Archived planned route (from the pirep_archive SimBrief navlog),
        // drawn as a distinct line from the live planned/actual routes above.
        // Same fail-soft contract as the map build.
        try {
            $navlog = $this->record->metadata->navlog ?? [];
            if ($navlog !== []) {
                $archivedLine = app(GeoService::class)->archivedRouteLine($navlog);
                $this->mapFeatures['archived_rte_line'] = json_decode((string) json_encode($archivedLine), true) ?? [];
            }
        } catch (Throwable $throwable) {
            Log::warning('PIREP archived route build failed', [
                'pirep_id' => $this->record->id,
                'error'    => $throwable->getMessage(),
            ]);
        }

        // Build chart payload (null when no ACARS data). Same fail-soft
        // contract: bad samples should not break the page, just hide the chart.
        try {
            $this->performance = app(PerformanceChartService::class)
                ->buildDatasets($this->record);
        } catch (Throwable $throwable) {
            Log::warning('PIREP performance chart build failed', [
                'pirep_id' => $this->record->id,
                'error'    => $throwable->getMessage(),
            ]);
            $this->performance = null;
        }
    }
}
