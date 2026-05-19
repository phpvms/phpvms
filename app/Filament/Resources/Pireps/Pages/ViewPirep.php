<?php

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use App\Services\Finance\PirepFinanceService;
use App\Services\GeoService;
use App\Services\Pirep\PerformanceChartService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

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
     * Custom blade view that renders the PIREP detail layout.
     * The page extends ViewRecord so Filament resolves the record from the
     * URL and applies policy checks; we just opt out of the default infolist
     * rendering and provide our own markup.
     */
    protected string $view = 'filament.resources.pireps.pages.view-pirep';

    #[\Override]
    public function getHeading(): string
    {
        $record = $this->getRecord();
        $parts = [$record->ident];
        if ($record->aircraft) {
            $parts[] = $record->aircraft->registration;
        }

        $parts[] = $record->dpt_airport_id.'→'.$record->arr_airport_id;

        return implode(' ', $parts);
    }

    #[\Override]
    public function content(Schema $schema): Schema
    {
        // No default infolist — the custom blade renders the detail layout.
        return $schema->components([]);
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
     * Skip ViewRecord's default form/infolist fill. The page renders a custom
     * blade that reads $record directly, so we don't need (or want) Filament
     * to hydrate a form schema from the model attributes. Pirep has custom
     * value-object casts (Fuel, Distance) which break NumberStateCast.
     */
    #[\Override]
    protected function fillForm(): void
    {
        // no-op
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            AcceptAction::make(),
            RejectAction::make(),
            EditAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[\Override]
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
        } catch (\Throwable $throwable) {
            Log::warning('PIREP map build failed', [
                'pirep_id' => $this->record->id,
                'error'    => $throwable->getMessage(),
            ]);
            $this->mapFeatures = [];
        }

        // Build chart payload (null when no ACARS data). Same fail-soft
        // contract: bad samples should not break the page, just hide the chart.
        try {
            $this->performance = app(PerformanceChartService::class)
                ->buildDatasets($this->record);
        } catch (\Throwable $throwable) {
            Log::warning('PIREP performance chart build failed', [
                'pirep_id' => $this->record->id,
                'error'    => $throwable->getMessage(),
            ]);
            $this->performance = null;
        }
    }
}
