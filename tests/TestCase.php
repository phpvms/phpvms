<?php

declare(strict_types=1);

namespace Tests;

use App\Services\SettingService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Override;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the settings table for every test.
     *
     * This lives here rather than in a `beforeEach` in tests/Pest.php because
     * that hook is scoped by `->in(...)` to the test directories, and Pest's
     * agent mode (`pest --agent=...`) generates its test file outside them, so
     * the hook never runs for it. Most code paths read settings, so an agent
     * snippet would otherwise execute against an empty settings table.
     *
     * `parent::setUp()` runs first so RefreshDatabase has prepared the schema,
     * which puts the seed at the same point in the lifecycle it occupied as a
     * `beforeEach`.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        // The seeder ships `general.auto_airport_lookup` ON, which makes
        // AirportService::lookupAirport() issue a LIVE HTTP request to
        // config('phpvms.api_url') (AirportService.php:86-88). Any test that
        // touches an unknown airport would then pass or fail on network
        // reachability and cache state rather than on the behaviour under test
        // -- and it did: `AirportTest > creates a generic airport` was flaky
        // for exactly this reason.
        //
        // Off by default for every test. A test that wants the lookup path
        // turns it back on itself AND fakes the HTTP response.
        app(SettingService::class)->store('general.auto_airport_lookup', false);
    }
}
