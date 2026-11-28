<?php

declare(strict_types=1);

use App\Filament\Pages\SetupWizard;
use App\Models\Airline;
use App\Models\User;
use App\Services\AirlineService;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    updateSetting('general.setup_completed', false);

    $this->admin = createAdminUser(['airline_id' => null]);

    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);
});

function wizardForm(): array
{
    return [
        'airline_icao'    => 'TST',
        'airline_name'    => 'Test Air',
        'airline_country' => 'us',
    ];
}

it('creates the airline, assigns it to the admin and marks setup complete', function (): void {
    Livewire::test(SetupWizard::class)
        ->fillForm(wizardForm())
        ->call('save')
        ->assertHasNoFormErrors();

    $airline = Airline::query()->where('icao', 'TST')->firstOrFail();

    expect($this->admin->refresh()->airline_id)->toBe($airline->id)
        ->and(setting('general.setup_completed'))->toBeTrue();
});

it('keeps nothing and leaves the gate closed when the save fails', function (): void {
    $airlines = Mockery::mock(AirlineService::class);
    $airlines->shouldReceive('createAirline')->andThrow(new RuntimeException('airline failed'));
    app()->instance(AirlineService::class, $airlines);

    Livewire::test(SetupWizard::class)
        ->fillForm(wizardForm())
        ->call('save');

    expect(Airline::query()->where('icao', 'TST')->exists())->toBeFalse()
        ->and(User::query()->whereKey($this->admin->id)->value('airline_id'))->toBeNull()
        ->and(setting('general.setup_completed'))->toBeFalse();
});

it('creates nothing when another tab completed setup after this one opened', function (): void {
    $component = Livewire::test(SetupWizard::class)->fillForm(wizardForm());

    updateSetting('general.setup_completed', true);

    $component->call('save')->assertRedirect(Filament::getUrl());

    expect(Airline::query()->where('icao', 'TST')->exists())->toBeFalse()
        ->and(User::query()->whereKey($this->admin->id)->value('airline_id'))->toBeNull();
});

it('redirects to the dashboard once setup is complete', function (): void {
    updateSetting('general.setup_completed', true);

    Livewire::test(SetupWizard::class)->assertRedirect(Filament::getUrl());
});

it('does not let a user without admin access open the wizard', function (): void {
    $this->actingAs(User::factory()->create());

    expect(SetupWizard::canAccess())->toBeFalse();
});
