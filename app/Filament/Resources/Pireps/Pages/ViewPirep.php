<?php

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use App\Services\GeoService;
use App\Services\Pirep\PerformanceChartService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

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
    public function content(Schema $schema): Schema
    {
        // No default infolist — the custom blade renders the detail layout.
        return $schema->components([]);
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

        // Eager-load everything the detail blade and embedded relation
        // managers need. 'fields' is an Attribute accessor — don't load it.
        $this->record->loadMissing([
            'user',
            'aircraft',
            'airline',
            'dpt_airport',
            'arr_airport',
            'comments.user',
            'transactions',
            'fares.fare',
            'field_values',
        ]);

        // GeoService returns FeatureCollection value objects; convert to plain
        // arrays so Livewire can serialize the property between requests.
        $features = app(GeoService::class)->pirepGeoJson($this->record);
        $this->mapFeatures = json_decode((string) json_encode($features), true) ?? [];

        // Build chart payload (null when no ACARS data).
        $this->performance = app(PerformanceChartService::class)
            ->buildDatasets($this->record);
    }
}
