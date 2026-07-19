<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Filament\Widgets\AirlineFinanceChart;
use App\Filament\Widgets\AirlineFinanceTable;
use App\Models\Airline;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Override;
use UnitEnum;

class Finances extends Page
{
    use AuthorizesAccess;
    use HasFiltersForm;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('common.finances');
    }

    public function filtersForm(Schema $schema): Schema
    {
        $startDate = setting('general.start_date') instanceof Carbon ? setting('general.start_date') : now();
        $minDate = $startDate->diffInSeconds() > 2 ? $startDate : now()->subYear();

        return $schema->components([
            Section::make()->schema([
                DatePicker::make('start_date')
                    ->label(__('common.start_date'))
                    ->native(false)
                    // Some magic cause if no start_date is set, now is returned
                    ->minDate($minDate)
                    ->maxDate(fn (Get $get): mixed => $get('end_date') ?: now()),

                DatePicker::make('end_date')
                    ->label(__('common.end_date'))
                    ->native(false)
                    ->minDate(fn (Get $get): mixed => $get('start_date'))
                    ->maxDate(now()),

                Select::make('airline_id')
                    ->native(false)
                    ->label(__('common.airline'))
                    ->searchable()
                    ->options(Airline::selectList(orderBy: 'name')),
            ])
                ->columnSpanFull()
                ->columns(3),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            AirlineFinanceChart::class,
            AirlineFinanceTable::class,
        ];
    }

    public function getFiltersFormContentComponent(): Component
    {
        return EmbeddedSchema::make('filtersForm');
    }

    public function getWidgetsContentComponent(): Component
    {
        return Grid::make()
            ->columns(1)
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
