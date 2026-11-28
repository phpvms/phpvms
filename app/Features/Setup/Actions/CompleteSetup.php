<?php

declare(strict_types=1);

namespace App\Features\Setup\Actions;

use App\Features\Setup\Support\SetupLock;
use App\Models\Airline;
use App\Models\User;
use App\Services\AirlineService;
use App\Services\SettingService;
use App\Settings\GeneralSettings;
use App\Support\Settings\SettingsCacheRefresher;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Saves the setup wizard in one transaction: the first airline, the admin's
 * airline, and the setup_completed flag.
 * A failure in any part keeps nothing, so the gate stays closed.
 * Returns null when another request already completed setup.
 */
final readonly class CompleteSetup
{
    public function __construct(
        private AirlineService $airlines,
        private SettingService $settings,
        private SettingsCacheRefresher $refresher,
        private SetupLock $lock,
    ) {}

    /**
     * @param array{icao: string, name: string, country: string} $airline
     */
    public function handle(User $user, array $airline): ?Airline
    {
        // A file lock, so two tabs submitting at once cannot both create an airline.
        if (!$this->lock->acquire(5)) {
            throw new RuntimeException(__('installer.setup_wizard.busy'));
        }

        try {
            // Another process may have completed setup since this one cached the flag.
            $this->refresher->refresh(app(GeneralSettings::class));

            if (setting('general.setup_completed', true)) {
                return null;
            }

            return $this->complete($user, $airline);
        } finally {
            $this->lock->release();
        }
    }

    /**
     * @param array{icao: string, name: string, country: string} $airline
     */
    private function complete(User $user, array $airline): Airline
    {
        try {
            return $this->save($user, $airline);
        } catch (Throwable $throwable) {
            // The settings cache was written before the rollback.
            $this->refresher->refresh(app(GeneralSettings::class));

            throw $throwable;
        }
    }

    /**
     * @param array{icao: string, name: string, country: string} $airline
     */
    private function save(User $user, array $airline): Airline
    {
        return DB::transaction(function () use ($user, $airline): Airline {
            $created = $this->airlines->createAirline($airline);

            $user->airline_id = $created->id;
            $user->save();

            // Last: the gate only opens once everything above is saved.
            $this->settings->store('general.setup_completed', true);

            return $created;
        });
    }
}
