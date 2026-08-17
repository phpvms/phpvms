<?php

namespace App\Filament\Resources\Fares\Pages;

use App\Enums\ImportExportType;
use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\FareExporter;
use App\Filament\Imports\FareImporter;
use App\Filament\Resources\Fares\FareResource;
use App\Models\Flight;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Override;

class ListFares extends ListRecords
{
    protected static string $resource = FareResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            OldExportAction::make('old-export')
                ->arguments(['resourceTitle' => 'fares', 'exportType' => ImportExportType::FARES]),

            OldImportAction::make('old-import')
                ->arguments(['resourceTitle' => 'fares', 'importType' => ImportExportType::FARES]),

            ImportAction::make('import')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->importer(FareImporter::class),

            ExportAction::make('export')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->exporter(FareExporter::class),

            CreateAction::make()
                ->icon(Phosphor::PlusCircleLight),

            Action::make('probe')
                ->label(__('filament.fare_probe'))
                ->icon(Phosphor::CalculatorLight)
                ->color('gray')
                ->modalHeading(__('filament.fare_probe'))
                ->modalDescription(__('filament.fare_probe_description'))
                ->modalWidth(Width::FourExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('common.close'))
                ->schema([
                    Select::make('flight_id')
                        ->label(trans_choice('common.flight', 1))
                        ->searchable()
                        ->live()
                        ->getSearchResultsUsing(fn (string $search): array => Flight::query()
                            ->where(fn (Builder $query) => $query
                                ->where('flight_number', 'like', "%{$search}%")
                                ->orWhere('dpt_airport_id', 'like', "%{$search}%")
                                ->orWhere('arr_airport_id', 'like', "%{$search}%"))
                            ->limit(25)
                            ->get()
                            ->mapWithKeys(fn (Flight $flight): array => [
                                $flight->id => $flight->ident.' · '.$flight->dpt_airport_id.' → '.$flight->arr_airport_id,
                            ])
                            ->all()),

                    View::make('filament.fares.probe-results')
                        ->viewData(fn (Get $get): array => [
                            'flight' => filled($get('flight_id'))
                                ? Flight::with(['subfleets.fares', 'fares', 'airline'])->find($get('flight_id'))
                                : null,
                        ]),
                ]),
        ];
    }
}
