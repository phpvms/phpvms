<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Filament\Clusters\Reports as ReportsCluster;
use App\Models\Airline;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Override;

/**
 * Shared behaviour for the Reports hub sub-pages (Flights, Pilots,
 * Aircraft): cluster membership, the period + airline filters, and the
 * page skeleton. The filter session key is shared across every sub-page so
 * the period selection is sticky when moving between them.
 */
abstract class BaseReportPage extends Page
{
    use HasFiltersForm;

    protected static ?string $cluster = ReportsCluster::class;

    /**
     * One session key for the whole Reports hub — not per class — so the
     * period/airline selection carries over between Flights, Pilots and
     * Aircraft.
     */
    public function getFiltersSessionKey(): string
    {
        return 'reports_filters';
    }

    public function filtersForm(Schema $schema): Schema
    {
        $startDate = setting('general.start_date') instanceof Carbon ? setting('general.start_date') : now();
        $minDate = $startDate->diffInSeconds() > 2 ? $startDate : now()->subYear();

        return $schema->components([
            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    DatePicker::make('start_date')
                        ->label(__('common.start_date'))
                        ->inlineLabel()
                        ->native(false)
                        ->minDate($minDate)
                        ->maxDate(fn (Get $get): mixed => $get('end_date') ?: now()),

                    DatePicker::make('end_date')
                        ->label(__('common.end_date'))
                        ->inlineLabel()
                        ->native(false)
                        ->minDate(fn (Get $get): mixed => $get('start_date'))
                        ->maxDate(now()),

                    Select::make('airline_id')
                        ->label(__('common.airline'))
                        ->inlineLabel()
                        ->native(false)
                        ->searchable()
                        ->options(Airline::selectList(orderBy: 'name')),
                ]),
        ]);
    }

    public function getFiltersFormContentComponent(): Component
    {
        return EmbeddedSchema::make('filtersForm');
    }

    /**
     * @return array<class-string<Widget>>
     */
    abstract public function getWidgets(): array;

    public function getWidgetsContentComponent(): Component
    {
        // 2 columns, like the dashboard: span-1 stat charts sit side-by-side
        // and span-full widgets (history tables, hbar charts) take a whole row.
        return Grid::make(2)
            ->columnSpanFull()
            ->schema($this->getWidgetsSchemaComponents($this->getWidgets()));
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFiltersFormContentComponent(),
                $this->getWidgetsContentComponent(),
            ]);
    }
}
