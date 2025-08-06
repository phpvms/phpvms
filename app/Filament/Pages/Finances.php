<?php

namespace App\Filament\Pages;

use App\Repositories\AirlineRepository;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class Finances extends Page
{
    use HasFiltersForm;
    use HasPageShield;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Finances';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.pages.finances';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                DatePicker::make('start_date')
                    ->native(false)
                    // Some magic cause if no start_date is set, now is returned
                    ->minDate(setting('general.start_date')->diffInSeconds() > 2 ? setting('general.start_date') : now()->subYear())
                    ->maxDate(fn (Get $get) => $get('end_date') ?: now()),

                DatePicker::make('end_date')
                    ->native(false)
                    ->minDate(fn (Get $get) => $get('start_date'))
                    ->maxDate(now()),

                Select::make('airline_id')
                    ->native(false)
                    ->label('Airline')
                    ->options(app(AirlineRepository::class)->selectBoxList()),
            ])
                ->columns(3),
        ]);
    }
}
