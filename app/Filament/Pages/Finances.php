<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AirlineFinanceChart;
use App\Filament\Widgets\AirlineFinanceTable;
use App\Models\Enums\NavigationGroup;
use App\Repositories\AirlineRepository;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
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

class Finances extends Page
{
    use HasFiltersForm;
    use HasPageShield;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function getNavigationLabel(): string
    {
        return __('common.finances');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                DatePicker::make('start_date')
                    ->label(__('common.start_date'))
                    ->native(false)
                    // Some magic cause if no start_date is set, now is returned
                    ->minDate(setting('general.start_date')->diffInSeconds() > 2 ? setting('general.start_date') : now()->subYear())
                    ->maxDate(fn (Get $get) => $get('end_date') ?: now()),

                DatePicker::make('end_date')
                    ->label(__('common.end_date'))
                    ->native(false)
                    ->minDate(fn (Get $get) => $get('start_date'))
                    ->maxDate(now()),

                Select::make('airline_id')
                    ->native(false)
                    ->label(__('common.airline'))
                    ->searchable()
                    ->options(app(AirlineRepository::class)->selectBoxList(order_by: 'name')),
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFiltersFormContentComponent(),
                $this->getWidgetsContentComponent(),
            ]);
    }
}
