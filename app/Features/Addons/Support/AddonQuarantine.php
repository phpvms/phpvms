<?php

declare(strict_types=1);

namespace App\Features\Addons\Support;

use App\Features\Addons\AddonRegistry;
use App\Models\Addon;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Takes an addon out of service when one of its declarations fails: a listener
 * that cannot be registered (a hook point that does not exist, or a listener
 * typed to a class that is not a hook point), a declaration method that throws,
 * or a provider that is not on the addon base. One broken third-party addon
 * therefore cannot stop every boot, including the admin panel the operator
 * needs to turn it off.
 *
 * The addon is disabled and the super-admins get a database notification in
 * the admin panel naming the addon, the source of the failure and the reason. A
 * bundled addon, or the active theme, cannot be disabled; those are logged
 * only, and the failing declaration is skipped.
 *
 * Never throws: this runs inside an addon provider's register() and boot().
 */
class AddonQuarantine
{
    public function __construct(private readonly AddonRegistry $registry) {}

    /**
     * @param  string $namespace PSR-4 root namespace of the addon, as stored on its row
     * @param  string $addon     short addon identifier, for the log line
     * @param  string $source    what failed: a listener FQCN, a provider FQCN or a method name
     * @return bool   whether the addon was disabled
     */
    public function handle(string $namespace, string $addon, string $source, Throwable $failure): bool
    {
        Log::error(sprintf('Addon "%s" quarantined: %s failed: %s', $addon, $source, $failure->getMessage()));

        if (!installed()) {
            return false;
        }

        try {
            $row = Addon::query()->where('namespace', $namespace)->first();

            if (!$row instanceof Addon) {
                Log::warning(sprintf('Addon "%s" has no addons row for namespace %s, so it was not disabled.', $addon, $namespace));

                return false;
            }

            if ($row->isBundled()) {
                Log::error(sprintf('Addon "%s" is bundled and cannot be disabled; %s is skipped.', $addon, $source));

                return false;
            }

            $this->registry->disable($row->name);
        } catch (RuntimeException $runtimeException) {
            // The active theme cannot be disabled from under the site.
            Log::error(sprintf('Addon "%s" could not be disabled: %s', $addon, $runtimeException->getMessage()));

            return false;
        } catch (Throwable $throwable) {
            Log::warning(sprintf('Addon "%s" quarantine failed: %s', $addon, $throwable->getMessage()));

            return false;
        }

        $this->notifySuperAdmins($row, $source, $failure);

        return true;
    }

    private function notifySuperAdmins(Addon $row, string $source, Throwable $failure): void
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
                ->body(sprintf('%s failed: %s Fix the addon, then enable it again.', $source, $failure->getMessage()))
                ->danger()
                ->persistent()
                ->sendToDatabase($admins);
        } catch (Throwable $throwable) {
            Log::warning(sprintf('Addon "%s" quarantine notification failed: %s', $row->name, $throwable->getMessage()));
        }
    }
}
