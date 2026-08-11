<?php

namespace App\Filament\Resources\Airports\Pages;

use App\Enums\ImportExportType;
use App\Filament\Actions\Drawer;
use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\AirportExporter;
use App\Filament\Imports\AirportImporter;
use App\Filament\Resources\Airports\AirportResource;
use App\Filament\Resources\Airports\Schemas\AirportForm;
use App\Models\Airport;
use App\Services\AirportService;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Override;

class ListAirports extends ListRecords
{
    protected static string $resource = AirportResource::class;

    public bool $hasAirportSearch = false;

    /** @var array<string, array<string, mixed>> */
    public array $airportSearchResults = [];

    /** @var array<string, array<string, mixed>> */
    public array $queuedAirports = [];

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            OldExportAction::make('old-export')
                ->arguments(['resourceTitle' => 'airports', 'exportType' => ImportExportType::AIRPORT]),

            OldImportAction::make('old-import')
                ->arguments(['resourceTitle' => 'airports', 'importType' => ImportExportType::AIRPORT]),

            ImportAction::make('import')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->importer(AirportImporter::class),

            ExportAction::make('export')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->exporter(AirportExporter::class),

            Drawer::configure(
                Action::make('addAirports')
                    ->label(__('airports.add_airports'))
                    ->icon(TablerIcon::CirclePlus)
                    ->visible(fn (): bool => Gate::allows('create', Airport::class))
                    ->modalHeading(__('airports.add_airports'))
                    ->modalDescription(__('airports.add_airports_description'))
                    ->modalSubmitActionLabel(__('airports.save_and_exit'))
                    ->mountUsing(function (Schema $schema): void {
                        $this->resetAddAirports();
                        $schema->fill();
                    })
                    ->action(fn (array $data) => $this->saveAirports($data)),
                [
                    Select::make('airportSearchSelection')
                        ->label(__('airports.airport_search'))
                        ->placeholder(__('airports.airport_search_placeholder'))
                        ->helperText(__('airports.airport_search_helper').' →')
                        ->searchable()
                        ->native(false)
                        ->autofocus()
                        ->live()
                        ->searchDebounce(300)
                        ->searchPrompt(__('airports.airport_search_helper'))
                        ->searchingMessage(__('airports.searching_airports'))
                        ->noSearchResultsMessage(__('airports.no_airports_found'))
                        ->getSearchResultsUsing(fn (string $search, AirportService $airportService): array => $this->searchAirports($search, $airportService))
                        ->getOptionLabelUsing(function (?string $value): ?string {
                            $airport = $this->airportSearchResults[$value] ?? null;

                            return $airport === null ? null : $airport['icao'].' - '.$airport['name'];
                        })
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            $this->queueSelectedAirport($state);
                            $set('airportSearchSelection', null);
                        })
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Group::make(AirportForm::components())
                        ->visible(fn (): bool => !$this->hasAirportSearch && $this->queuedAirports === []),
                    RepeatableEntry::make('queuedAirports')
                        ->label(__('airports.airports_to_add'))
                        ->state(fn (): array => array_values($this->queuedAirports))
                        ->visible(fn (): bool => $this->queuedAirports !== [])
                        ->schema([
                            TextEntry::make('title')
                                ->hiddenLabel()
                                ->weight(FontWeight::Bold),
                            TextEntry::make('timezone')
                                ->label(__('common.timezone'))
                                ->inlineLabel()
                                ->size(TextSize::Small),
                            TextEntry::make('display_location')
                                ->label(__('user.location'))
                                ->inlineLabel()
                                ->size(TextSize::Small),
                        ])
                        ->columns(1)
                        ->columnSpanFull(),
                ],
                Width::TwoExtraLarge,
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function searchAirports(string $search, AirportService $airportService): array
    {
        if (!Gate::allows('create', Airport::class)) {
            Notification::make()->title(__('common.not_authorized'))->danger()->send();

            return [];
        }

        $search = trim($search);
        $this->hasAirportSearch = $search !== '';

        if ($search === '') {
            $this->airportSearchResults = [];

            return [];
        }

        $this->airportSearchResults = collect($airportService->searchAirports($search))
            ->keyBy('icao')
            ->all();

        return collect($this->airportSearchResults)
            ->mapWithKeys(fn (array $airport): array => [
                $airport['icao'] => $airport['icao'].' - '.$airport['name'],
            ])
            ->all();
    }

    public function queueSelectedAirport(string $icao): void
    {
        $airport = $this->airportSearchResults[$icao] ?? null;

        if ($airport !== null) {
            $this->queueAirport($airport);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveAirports(array $data): void
    {
        if (!Gate::allows('create', Airport::class)) {
            Notification::make()->title(__('common.not_authorized'))->danger()->send();

            return;
        }

        $airports = $this->queuedAirports !== [] ? $this->queuedAirports : [$data];

        DB::transaction(function () use ($airports): void {
            foreach ($airports as $attributes) {
                $icao = strtoupper((string) $attributes['icao']);
                $airport = Airport::withTrashed()->firstOrNew(['id' => $icao]);
                $airport->fill($attributes);

                if ($airport->trashed()) {
                    $airport->restore();
                }

                $airport->save();
            }
        });

        Notification::make()
            ->title(__('airports.airports_saved'))
            ->success()
            ->send();

        $this->resetTable();
        $this->resetAddAirports();
    }

    /**
     * @param array<string, mixed> $airport
     */
    protected function queueAirport(array $airport): void
    {
        $icao = (string) $airport['icao'];

        if (isset($this->queuedAirports[$icao]) || Airport::withTrashed()->whereKey($icao)->exists()) {
            Notification::make()
                ->title(__('airports.airport_already_exists', ['icao' => $icao]))
                ->warning()
                ->send();

            $this->hasAirportSearch = false;
            $this->airportSearchResults = [];

            return;
        }

        $region = Str::after((string) $airport['region'], '-');
        $location = collect([$airport['location'], $region, $airport['country']])
            ->filter()
            ->unique()
            ->implode(', ');

        $this->queuedAirports[$icao] = [
            ...$airport,
            'title'            => $icao.' - '.$airport['name'],
            'display_location' => $location,
        ];

        $this->hasAirportSearch = false;
        $this->airportSearchResults = [];
    }

    protected function resetAddAirports(): void
    {
        $this->hasAirportSearch = false;
        $this->airportSearchResults = [];
        $this->queuedAirports = [];
    }
}
