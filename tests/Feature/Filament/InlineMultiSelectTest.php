<?php

use App\Filament\Forms\Components\InlineMultiSelect;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;

/**
 * `$form` is materialised by InteractsWithSchemas at runtime; Filament's own
 * pages declare it the same way (see Filament\Resources\Pages\EditRecord).
 *
 * @property-read Schema $form
 */
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

test('options nested under a group label flatten into the checklist', function (): void {
    $items = InlineMultiSelect::make('equipment')
        ->options([
            'Boeing' => ['1' => 'B737-800', '2' => 'B747-400'],
            'Airbus' => ['3' => 'A320'],
        ])
        ->optionMetas(['3' => 'A320'])
        ->getItems();

    // Sorted by group, declaration order kept inside each one.
    expect($items)->toBe([
        ['value' => '3', 'label' => 'A320', 'meta' => 'A320', 'group' => 'Airbus'],
        ['value' => '1', 'label' => 'B737-800', 'meta' => null, 'group' => 'Boeing'],
        ['value' => '2', 'label' => 'B747-400', 'meta' => null, 'group' => 'Boeing'],
    ]);
});
