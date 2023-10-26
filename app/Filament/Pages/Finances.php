<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AirlineFinanceChart;
use App\Filament\Widgets\AirlineFinanceTable;
use App\Repositories\AirlineRepository;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Modelable;

class Finances extends Page
{
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Finances';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.finances';

    public ?array $filters = [];


    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $data = [
            'start_date' => now()->subYear(),
            'end_date'   => now(),
            'airline_id' => Auth::user()->airline_id
        ];

        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    public function form(Form $form): Form
    {
        return $form->statePath('filters')->schema([
            Forms\Components\DatePicker::make('start_date')->native(false)->maxDate(now())->live(),
            Forms\Components\DatePicker::make('end_date')->native(false)->maxDate(now())->live(),
            Forms\Components\Select::make('airline_id')->label('Airline')->options(app(AirlineRepository::class)->selectBoxList())->live(),
        ])->columns(3);
    }

    public function test()
    {
        $this->dispatch('updateFinanceChart', start_date: $this->filters['start_date'], end_date: $this->filters['end_date'], airline_id: $this->filters['airline_id']);

    }
}
