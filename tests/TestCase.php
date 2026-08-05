<?php

declare(strict_types=1);

namespace Tests;

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
    }
}
