<?php

use App\Models\Pirep;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\DatabaseService;
use App\Services\SettingService;
use App\Services\UserService;

/**
 * Load the given yaml file into the database
 */
function loadYamlIntoDb(string $file): void
{
    $file_path = base_path('tests/data/'.$file.'.yml');
    app(DatabaseService::class)->seed_from_yaml_file($file_path);
}

/**
 * Update the given setting key with the given value
 */
function updateSetting(string $key, $value): void
{
    app(SettingService::class)->store($key, $value);
}

/**
 * Create an admin user
 */
function createAdminUser(array $attrs = []): User
{
    $admin = User::factory()->create($attrs);

    $userSvc = app(UserService::class);
    $userSvc->addUserToRole($admin, 'super_admin');

    return $admin;
}

/**
 * Create a new PIREP with a proper subfleet/rank/user and an
 * aircraft that the user is allowed to fly
 */
function createPirep(array $user_attrs = [], array $pirep_attrs = []): Pirep
{
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    $user = User::factory()->create(array_merge([
        'rank_id' => $rank->id,
    ], $user_attrs));

    // Return a Pirep model
    return Pirep::factory()->make(array_merge([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet->aircraft->random()->id,
    ], $pirep_attrs));
}
