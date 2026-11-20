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
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

function installerEngineStep(SetupStep $which, bool $throws = false): Step
{
    return new readonly class($which, $throws) implements Step
    {
        public function __construct(private SetupStep $which, private bool $throws) {}

        public function step(): SetupStep
        {
            return $this->which;
        }

        public function applies(StepContext $ctx): bool
        {
            return true;
        }

        public function run(StepContext $ctx): void
        {
            if ($this->throws) {
                throw new RuntimeException('step exploded');
            }
        }
    };
}

/**
 * @param list<Step> $steps
 */
function bindInstallerEngine(array $steps, ?UpgradeCompleted $completed = null): void
{
    app()->bind(RunSetup::class, fn (): RunSetup => new RunSetup(
        app(SetupRunState::class),
        app(SetupLock::class),
        $steps,
        $completed ?? app(UpgradeCompleted::class),
    ));
}

test('installing writes the completion file, so /system/upgrade is 404', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    // Livewire test requests carry no session, and save() regenerates it after login.
    app()->rebinding('request', fn ($app, $request) => $request->setLaravelSession(app('session.store')));

    // The engine writes its own completion file elsewhere, so only save() can write the real one.
    $engineCompleted = storage_path('framework/testing/installer-engine-completed-'.uniqid());
    bindInstallerEngine([installerEngineStep(SetupStep::Migrate)], new UpgradeCompleted($engineCompleted));

    Livewire::test(Installer::class)
        ->call('runMigrations')
        ->set('user', [
            'airline_icao'          => 'VMS',
            'airline_name'          => 'phpvms Air',
            'airline_country'       => 'us',
            'name'                  => 'First Admin',
            'email'                 => 'admin@example.com',
            'password'              => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/admin');

    @unlink($engineCompleted);

    expect(app(UpgradeCompleted::class)->exists())->toBeTrue();

    $this->get('/system/upgrade')->assertNotFound();
});

test('a failed engine step shows the step and keeps the installer open', function (): void {
    bindInstallerEngine([
        installerEngineStep(SetupStep::Migrate),
        installerEngineStep(SetupStep::PassportKeys, throws: true),
    ]);

    Livewire::test(Installer::class)
        ->call('runMigrations')
        ->assertSet('migrationOutput', fn (string $output): bool => str_contains($output, SetupStep::PassportKeys->value)
            && str_contains($output, 'step exploded')
            && !str_contains($output, __('installer.migrations_completed')))
        ->assertNotDispatched('installer-migrations-complete')
        ->assertNoRedirect();

    expect(Installer::canAccess())->toBeTrue();
});
