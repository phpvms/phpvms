<?php

use App\Filament\Concerns\AutosavesFields;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Harness component: a bare Livewire form using the trait, persisting into
 * a public array so the test can observe what was saved.
 */
class AutosaveHarness extends Component implements HasForms
{
    use AutosavesFields;
    use InteractsWithForms;

    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $persisted = [];

    public bool $allowed = true;

    public function mount(): void
    {
        $this->form->fill(['name' => 'before']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated($this->autosave()),
            ])
            ->statePath('data');
    }

    protected function autosaveKeys(): array
    {
        return ['name'];
    }

    protected function persistAutosavedField(string $key, mixed $value): void
    {
        $this->persisted[$key] = $value;
    }

    protected function canAutosave(): bool
    {
        return $this->allowed;
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('a live field change persists through the trait and notifies', function (): void {
    Livewire::test(AutosaveHarness::class)
        ->set('data.name', 'after')
        ->assertSet('persisted.name', 'after')
        ->assertNotified(__('common.saved'));
});

test('autosave is refused when the page disallows it', function (): void {
    Livewire::test(AutosaveHarness::class, ['allowed' => false])
        ->set('data.name', 'after')
        ->assertStatus(403);
});
