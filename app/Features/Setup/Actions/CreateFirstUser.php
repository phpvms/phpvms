<?php

declare(strict_types=1);

namespace App\Features\Setup\Actions;

use App\Enums\UserState;
use App\Features\Setup\Support\SetupLock;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use App\Services\UserService;
use App\Settings\GeneralSettings;
use App\Support\InstallMarker;
use App\Support\Settings\SettingsCacheRefresher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;
use Throwable;

/**
 * Creates the first super admin of a new site, once. Returns null when a user
 * already exists (a second visitor won the race) or the setup lock is held.
 */
final readonly class CreateFirstUser
{
    public function __construct(
        private SetupLock $lock,
        private UserService $users,
        private SettingService $settings,
        private SettingsCacheRefresher $refresher,
        private InstallMarker $marker,
    ) {}

    /**
     * @param array{name?: ?string, email: string, password: string} $input
     */
    public function handle(#[SensitiveParameter] array $input): ?User
    {
        // A file lock, because the cache and sessions tables may not exist on a site this new.
        if (!$this->lock->acquire(5)) {
            return null;
        }

        try {
            try {
                $user = DB::transaction(function () use ($input): ?User {
                    if (User::withTrashed()->exists()) {
                        return null;
                    }

                    $user = $this->users->createUser([
                        'name'              => $input['name'] ?? null,
                        'email'             => $input['email'],
                        'password'          => Hash::make($input['password']),
                        'opt_in'            => true,
                        'state'             => UserState::ACTIVE,
                        'email_verified_at' => now(),
                    ], [Role::superAdminName()], UserState::ACTIVE->value);

                    // In the transaction so a failure cannot leave a user with the wizard skipped.
                    // The second flag holds the gate closed until the setup wizard has run.
                    $this->settings->storeMany([
                        'general.admin_email'     => $user->email,
                        'general.setup_completed' => false,
                    ]);

                    return $user;
                });
            } catch (Throwable $throwable) {
                // The settings cache was written before the rollback.
                $this->refresher->refresh(app(GeneralSettings::class));

                throw $throwable;
            }

            if (!$user instanceof User) {
                return null;
            }

            $this->marker->write();

            return $user;
        } finally {
            $this->lock->release();
        }
    }
}
