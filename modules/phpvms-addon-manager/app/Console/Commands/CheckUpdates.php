<?php

declare(strict_types=1);

namespace Modules\AddonManager\Console\Commands;

use App\Addons\AddonRegistry;
use App\Models\Addon;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\AddonManager\Services\CompatibilityEvaluator;
use Modules\AddonManager\Services\RegistryClient;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Refresh the registry catalog and notify admins of addons with a newer
 * published version. Scheduled by the module provider (cadence configurable via
 * `addon-manager.update_check_cadence`).
 *
 * The nav badge count is derived live from catalog-vs-installed on every admin
 * render, so this command's only job is the one-shot bell notification — sent
 * once per addon+version and deduplicated across re-runs.
 */
#[AsCommand(name: 'addons:check-updates', description: 'Check the registry for addon updates and notify admins')]
#[Description('Check the registry for addon updates and notify admins')]
#[Signature('addons:check-updates')]
class CheckUpdates extends Command
{
    public function handle(RegistryClient $registry, CompatibilityEvaluator $evaluator): int
    {
        $catalog = $registry->refresh();

        if ($catalog['error'] !== null) {
            $this->components->error('Could not reach the registry: '.$catalog['error']);

            return self::FAILURE;
        }

        $lookup = collect($catalog['entries'])
            ->keyBy(fn (array $entry): string => Str::lower((string) $entry['registry_id']));

        $admins = $this->admins();
        $found = 0;

        foreach (app(AddonRegistry::class)->all() as $addon) {
            $entry = $lookup->get(Str::lower($addon->registry_id ?: $addon->getName()));
            $latest = (string) ($entry['version'] ?? '');
            if ($entry === null) {
                continue;
            }
            if ($latest === '') {
                continue;
            }
            if (!$evaluator->isNewer($latest, (string) $addon->version)) {
                continue;
            }

            $found++;
            $this->notifyUpdate($addon, $entry, $latest, $admins);
        }

        $this->components->info(sprintf('%d addon update(s) available.', $found));

        return self::SUCCESS;
    }

    /**
     * Send one bell notification per admin for an outstanding update, skipping
     * addon+version pairs already notified so daily re-runs don't repeat.
     *
     * @param array<string, mixed>  $entry
     * @param Collection<int, User> $admins
     */
    private function notifyUpdate(Addon $addon, array $entry, string $latest, Collection $admins): void
    {
        // ponytail: forever marker, unbounded but tiny (a handful of addons);
        // never cleared once a version is superseded. Prune only if it ever grows.
        // No admins to notify: don't burn the marker, so a super_admin added
        // later still gets notified about this outstanding version.
        if ($admins->isEmpty()) {
            return;
        }

        $marker = 'addon-manager:notified:'.Str::lower($addon->registry_id ?: $addon->getName()).'@'.$latest;

        if ($this->cache()->has($marker)) {
            return;
        }

        foreach ($admins as $admin) {
            Notification::make()
                ->title(sprintf('%s %s is available', $entry['name'], $latest))
                ->body(sprintf('You have %s. Update it from the Addons page.', $addon->version))
                ->info()
                ->sendToDatabase($admin);
        }

        $this->cache()->forever($marker, true);
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(): Collection
    {
        return User::whereHas('roles', function ($query): void {
            $query->where('name', Role::superAdminName());
        })->get();
    }

    /**
     * The module's persistent cache store, so the dedup marker survives between
     * daily cron processes. Mirrors RegistryClient::store()/InstallProgress.
     */
    private function cache(): Repository
    {
        $configured = config('addon-manager.cache_store');
        $store = (is_string($configured) && $configured !== '')
            ? $configured
            : (config('cache.default') === 'array' ? 'file' : null);

        return Cache::store($store);
    }
}
