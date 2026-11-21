<?php

declare(strict_types=1);

use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\System\Installer;
use App\Models\User;
use Livewire\Livewire;

/**
 * The installer page is public until the first user exists. A visitor who
 * loaded it before the operator finished holds a validly signed Livewire
 * snapshot; replaying it against /livewire/update after the install must be
 * refused on every request, not only on the page GET. Filament re-runs
 * canAccess() from hydrateCanAuthorizeAccess(), which is what these pin down.
 */
beforeEach(function (): void {
    // No engine step may ever run: a regression here would migrate and seed
    // the database from a replayed request.
    $this->ran = new ArrayObject();
    $steps = array_map(fn (SetupStep $case): Step => new readonly class($case, $this->ran) implements Step
    {
        public function __construct(private SetupStep $case, private ArrayObject $ran) {}

        public function step(): SetupStep
        {
            return $this->case;
        }

        public function applies(StepContext $ctx): bool
        {
            return true;
        }

        public function run(StepContext $ctx): void
        {
            $this->ran->append($this->case);
        }
    }, SetupStep::cases());

    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(
        app(SetupRunState::class),
        app(SetupLock::class),
        $steps,
        app(UpgradeCompleted::class),
    ));
});

afterEach(function (): void {
    expect($this->ran->getArrayCopy())->toBe([]);
});

it('refuses a replayed save once the first user exists', function (): void {
    // Two pre-install snapshots: a 403 response carries no snapshot, so each
    // replayed request needs its own.
    $filled = Livewire::test(Installer::class);
    $bare = Livewire::test(Installer::class);

    User::factory()->create();

    $filled
        ->set('user', [
            'airline_icao'          => 'EVL',
            'airline_name'          => 'Evil Air',
            'airline_country'       => 'US',
            'name'                  => 'Intruder',
            'email'                 => 'intruder@example.com',
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->assertForbidden();

    $bare->call('save')->assertForbidden();

    expect(User::query()->where('email', 'intruder@example.com')->exists())->toBeFalse();
});

it('refuses a replayed migration run once the first user exists', function (): void {
    $component = Livewire::test(Installer::class);

    User::factory()->create();

    $component->call('runMigrations')->assertForbidden();
});
