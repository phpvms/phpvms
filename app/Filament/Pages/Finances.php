<?php

namespace App\Filament\Pages;

use App\Repositories\AirlineRepository;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;

class Finances extends Page
{
    use HasFiltersForm;
    use HasPageShield;

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Finances';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.finances';

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\DatePicker::make('start_date')
                    ->native(false)
                    // Some magic cause if no start_date is set, now is returned
                    ->minDate(setting('general.start_date')->diffInSeconds() > 2 ? setting('general.start_date') : now()->subYear())
                    ->maxDate(fn (Get $get) => $get('end_date') ?: now()),

                Forms\Components\DatePicker::make('end_date')
                    ->native(false)
                    ->minDate(fn (Get $get) => $get('start_date'))
                    ->maxDate(now()),

                Forms\Components\Select::make('airline_id')
                    ->native(false)
                    ->label('Airline')
                    ->options(app(AirlineRepository::class)->selectBoxList()),
            ])
                ->columns(3),
        ]);
    }
}
