<?php

use App\Exceptions\AutosaveFailed;
use App\Filament\Concerns\AutosavesFields;
use App\Filament\Resources\Airlines\Pages\EditAirline;
use App\Http\Middleware\UpdatePending;
use App\Models\Airline;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Harness component: a bare Livewire form using the trait, persisting into
 * a public array so the test can observe what was saved.
 *
 * `$form` is materialised by InteractsWithSchemas at runtime; Filament's own
 * pages declare it the same way (see Filament\Resources\Pages\EditRecord).
 *
 * @property-read Schema $form
 */
class AutosaveHarness extends Component implements HasForms
{
    use AutosavesFields;
    use InteractsWithForms;

    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $persisted = [];

    public bool $allowed = true;

    /** Makes persistAutosavedField() report a failure the admin can act on. */
    public bool $fails = false;

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
        if ($this->fails) {
            throw new AutosaveFailed('Could not save the thing');
        }

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
        ->assertDispatched('autosaved', statePath: 'data.name')
        ->assertNotified(__('common.saved'));
});

test('a failed autosave reports the failure', function (): void {
    Livewire::test(AutosaveHarness::class, ['fails' => true])
        ->set('data.name', 'after')
        ->assertNotified('Could not save the thing');
});

/**
 * The success toast used to fire regardless, so a call site that reported its
 * own failure got "could not be saved" followed immediately by "saved". The
 * saved tick is part of the same lie and must stay away too.
 *
 * Its own Livewire run, NOT chained onto the assertion above: Filament reads
 * notifications with `session()->pull()` (Livewire\Notifications@35), so the
 * first assertion drains the queue and any assertNotNotified chained after it
 * passes no matter what was sent.
 */
test('a failed autosave does not also report success', function (): void {
    Livewire::test(AutosaveHarness::class, ['fails' => true])
        ->set('data.name', 'after')
        ->assertNotNotified(__('common.saved'))
        ->assertNotDispatched('autosaved')
        ->assertSet('persisted', []);
});

test('autosave is refused when the page disallows it', function (): void {
    Livewire::test(AutosaveHarness::class, ['allowed' => false])
        ->set('data.name', 'after')
        ->assertStatus(403);
});

test('the airline edit page mounts with logo autosave wired', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);
    $this->actingAs(createAdminUser());

    $airline = Airline::factory()->create();

    Livewire::test(EditAirline::class, ['record' => $airline->id])
        ->assertSuccessful();
});
