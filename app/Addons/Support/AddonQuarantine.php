<?php

declare(strict_types=1);

namespace App\Addons\Support;

use App\Addons\AddonRegistry;
use App\Models\Addon;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Takes an addon out of service when one of its listeners cannot be registered
 * (a hook point that does not exist, or a listener typed to a class that is not
 * a hook point), so one broken third-party addon cannot stop every boot,
 * including the admin panel the operator needs to turn it off.
 *
 * The addon is disabled and the super-admins get a database notification in
 * the admin panel naming the addon, the listener and the reason. A bundled
 * addon, or the active theme, cannot be disabled; those are logged only, and
 * the listener is skipped.
 *
 * Never throws: this runs inside an addon provider's boot().
 */
class AddonQuarantine
{
    public function __construct(private readonly AddonRegistry $registry) {}

    /**
     * @param string $namespace PSR-4 root namespace of the addon, as stored on its row
     * @param string $addon     short addon identifier, for the log line
     * @param string $listener  FQCN of the listener that failed to register
     */
    public function handle(string $namespace, string $addon, string $listener, Throwable $failure): void
    {
        Log::error(sprintf('Addon "%s" quarantined: listener %s could not be registered: %s', $addon, $listener, $failure->getMessage()));

        if (!installed()) {
            return;
        }

        try {
            $row = Addon::query()->where('namespace', $namespace)->first();

            if (!$row instanceof Addon) {
                Log::warning(sprintf('Addon "%s" has no addons row for namespace %s, so it was not disabled.', $addon, $namespace));

                return;
            }

            if ($row->isBundled()) {
                Log::error(sprintf('Addon "%s" is bundled and cannot be disabled; its listener %s is skipped.', $addon, $listener));

                return;
            }

            $this->registry->disable($row->name);
        } catch (RuntimeException $runtimeException) {
            // The active theme cannot be disabled from under the site.
            Log::error(sprintf('Addon "%s" could not be disabled: %s', $addon, $runtimeException->getMessage()));

            return;
        } catch (Throwable $throwable) {
            Log::warning(sprintf('Addon "%s" quarantine failed: %s', $addon, $throwable->getMessage()));

            return;
        }

        $this->notifySuperAdmins($row, $listener, $failure);
    }

    private function notifySuperAdmins(Addon $row, string $listener, Throwable $failure): void
    {
        try {
            $admins = User::query()->whereHas('roles', function ($query): void {
                $query->where('name', Role::superAdminName());
            })->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::make()
                ->title(sprintf('Addon "%s" was disabled', $row->name))
                ->body(sprintf('Its listener %s could not be registered: %s Fix the addon, then enable it again.', $listener, $failure->getMessage()))
                ->danger()
                ->persistent()
                ->sendToDatabase($admins);
        } catch (Throwable $throwable) {
            Log::warning(sprintf('Addon "%s" quarantine notification failed: %s', $row->name, $throwable->getMessage()));
        }
    }
}
