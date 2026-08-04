<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Http\Middleware\UpdatePending;
use App\Models\Pirep;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $role = Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->withoutMiddleware(UpdatePending::class);
    actingAs($user);
});

it('renders the admin dashboard without error', function (): void {
    get('/admin')->assertOk();
});

it('renders the reports page under operations', function (): void {
    get('/admin/reports')
        ->assertOk()
        ->assertSee('Operational Reports');
});

it('filters the pireps list from the calendar deep-link', function (): void {
    $pirep = Pirep::factory()->create([
        'block_off_time' => '2026-08-02 01:30:00',
        'state'          => PirepState::ACCEPTED,
    ]);
    $other = Pirep::factory()->create([
        'block_off_time' => '2026-08-03 01:30:00',
        'state'          => PirepState::ACCEPTED,
    ]);

    get('/admin/pireps?departed_from=2026-08-02%2001:00:00&departed_to=2026-08-02%2001:59:59')
        ->assertOk()
        ->assertSee($pirep->flight->flight_number)
        ->assertDontSee($other->flight->flight_number);
});
