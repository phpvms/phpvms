<?php

use App\Filament\Forms\Components\InlineMultiSelect;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;

class InlineMultiSelectHarness extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                InlineMultiSelect::make('equipment')
                    ->options([
                        '1' => 'Boeing 737-800',
                        '2' => 'Airbus A320',
                    ])
                    ->optionMetas([
                        '1' => 'B738',
                        '2' => 'A320',
                    ]),
            ])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('options and metas serialize into the checklist and state round-trips', function (): void {
    Livewire::test(InlineMultiSelectHarness::class)
        ->assertSee('Boeing 737-800')
        ->assertSee('B738')
        ->set('data.equipment', ['1', '2'])
        ->assertSet('data.equipment', ['1', '2']);
});
