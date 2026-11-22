<?php

declare(strict_types=1);

use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\System\Installer;
use App\Models\User;
use Livewire\Livewire;

/**
 * Seed data can create users (the demo environment ships an admin). The
 * installer closes itself as soon as a user exists, so the migration step must
 * notice and hand over to the login page instead of advancing to a user/airline
 * step whose submit would be refused.
 *
 * The engine runs fake steps: the real ones would migrate and seed in-request.
 */
function mockInstallRun(bool $seedCreatesUser): void
{
    $seed = new readonly class($seedCreatesUser) implements Step
    {
        public function __construct(private bool $seedCreatesUser) {}

        public function step(): SetupStep
        {
            return SetupStep::Seed;
        }

        public function applies(StepContext $ctx): bool
        {
            return true;
        }

        public function run(StepContext $ctx): void
        {
            if ($this->seedCreatesUser) {
                User::factory()->create();
            }
        }
    };

    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(
        app(SetupRunState::class),
        app(SetupLock::class),
        [$seed],
        app(UpgradeCompleted::class),
    ));

    app()->instance(MigrationPaths::class, new class() extends MigrationPaths
    {
        public function pendingSchema(): array
        {
            return [];
        }

        public function pendingData(): array
        {
            return [];
        }
    });
}

it('sends the operator to the login page when the seed already created a user', function (): void {
    mockInstallRun(seedCreatesUser: true);

    $component = Livewire::test(Installer::class)->call('runMigrations');

    $component
        ->assertNotDispatched('installer-migrations-complete')
        ->assertSet('migrationOutput', fn (string $output): bool => str_contains($output, __('installer.user_already_seeded')));

    expect(collect($component->effects['xjs'] ?? [])->pluck('expression')->implode(' '))->toContain(json_encode(route('login')));
});

it('counts down to the user and airline step when the seed created no user', function (): void {
    mockInstallRun(seedCreatesUser: false);

    $component = Livewire::test(Installer::class)->call('runMigrations');

    $component
        ->assertDispatched('installer-migrations-complete')
        ->assertSet('migrationOutput', fn (string $output): bool => !str_contains($output, __('installer.user_already_seeded')));

    expect($component->effects['xjs'] ?? [])->toBeEmpty();
});
