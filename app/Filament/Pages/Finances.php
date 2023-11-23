<?php

namespace App\Filament\Pages;

use App\Repositories\AirlineRepository;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class Finances extends Page
{
    use HasPageShield;

    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Finances';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.finances';

    #[Url]
    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'start_date' => $this->filters['start_date'] ?? now()->subYear(),
            'end_date'   => $this->filters['end_date'] ?? now(),
            'airline_id' => $this->filters['airline_id'] ?? Auth::user()->airline_id,
        ];
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->filters);

        $this->callHook('afterFill');
    }

    public function form(Form $form): Form
    {
        return $form->statePath('filters')->schema([
            Forms\Components\DatePicker::make('start_date')->native(false)->maxDate(fn (Get $get) => $get('end_date'))->live()->afterStateUpdated(function () { $this->filtersUpdated(); }),
            Forms\Components\DatePicker::make('end_date')->native(false)->minDate(fn (Get $get) => $get('start_date'))->maxDate(now())->live()->afterStateUpdated(function () { $this->filtersUpdated(); }),
            Forms\Components\Select::make('airline_id')->label('Airline')->options(app(AirlineRepository::class)->selectBoxList())->live()->afterStateUpdated(function (?string $state) {
                if (!$state || $state == '') {
                    $this->filters['airline_id'] = Auth::user()->airline_id;
                } $this->filtersUpdated();
            }),
        ])->columns(3);
    }

    public function filtersUpdated()
    {
        $this->dispatch('updateFinanceFilters', start_date: $this->filters['start_date'] ?? now()->subYear(), end_date: $this->filters['end_date'], airline_id: $this->filters['airline_id']);
    }
}
