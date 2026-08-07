<?php

use App\Filament\Forms\Components\InlineMultiSelect;
use App\Filament\Resources\Typeratings\Pages\EditTyperating;
use App\Http\Middleware\UpdatePending;
use App\Models\Subfleet;
use App\Models\Typerating;
use Database\Seeders\RolesPermissionsSeeder;
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

test('the typerating form renders subfleet options in relationship mode', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);
    $this->actingAs(createAdminUser());

    // No TyperatingFactory exists; the model is simple enough to create raw.
    $typerating = Typerating::create([
        'name'   => 'B777 Rating',
        'type'   => 'B77X',
        'active' => true,
    ]);
    $subfleet = Subfleet::factory()->create(['name' => 'Longhaul 777', 'type' => 'B77W']);

    Livewire::test(EditTyperating::class, ['record' => $typerating->id])
        ->assertSuccessful()
        ->assertSee('Longhaul 777')
        ->assertSee('B77W');
});
