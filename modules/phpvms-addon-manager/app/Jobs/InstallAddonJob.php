<?php

declare(strict_types=1);

namespace Modules\AddonManager\Jobs;

use App\Addons\AddonRegistry;
use App\Addons\Sources\UrlSource;
use App\Addons\Support\ManifestParser;
use App\Addons\Support\RegistrySignatureVerifier;
use App\Exceptions\AddonInstallException;
use App\Models\Addon;
use App\Models\User;
use App\Services\CronService;
use App\Services\Installer\MigrationService;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\AddonManager\Services\RegistryClient;
use Modules\AddonManager\Support\InstallProgress;
use Throwable;

/**
 * Installs or updates an addon from the registry: mint → verify signature →
 * download → verify sha256 → place → optional migrations, reporting progress
 * and delivering a completion notification.
 *
 * One job at a time per addon (WithoutOverlapping on the registry id); a second
 * dispatch while one is running is dropped, and the UI shows the in-progress
 * state.
 */
class InstallAddonJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $registryId,
        public readonly string $version,
        public readonly bool $runMigrations,
        public readonly int $userId,
    ) {}

    /**
     * Dispatch the install, falling back to synchronous execution when no queue
     * worker or queue-cron will process it — otherwise a stock install would sit
     * "queued" forever. Returns true when it ran synchronously (the caller warns
     * the admin).
     */
    public static function dispatchFor(string $registryId, string $version, bool $runMigrations, int $userId): bool
    {
        $job = new self($registryId, $version, $runMigrations, $userId);

        // Async only when something will actually drain the queue: not the `sync`
        // driver, cron-draining enabled, AND the cron is actually alive (a
        // configured-but-dead cron would leave the job queued forever, which is
        // exactly what this fallback exists to prevent).
        $willBeProcessed = config('queue.default') !== 'sync'
            && (bool) config('phpvms.run_queued_jobs_in_cron', false)
            && !app(CronService::class)->cronProblemExists();

        if ($willBeProcessed) {
            Bus::dispatch($job);

            return false;
        }

        Bus::dispatchSync($job);

        return true;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->registryId)->dontRelease()];
    }

    public function handle(RegistryClient $client, RegistrySignatureVerifier $verifier, AddonRegistry $registry): void
    {
        try {
            InstallProgress::set($this->registryId, 'minting', 10, 'Requesting a download from the registry…');

            $mint = $client->mintDownload($this->registryId, $this->version);

            if ($mint['status'] >= 400) {
                // Yanked release / artifact not yet uploaded: refresh the catalog
                // so the UI self-corrects, then fail with a clear message.
                $client->refresh();

                throw new AddonInstallException($this->mintErrorMessage($mint['status']));
            }

            // Verify the Ed25519 signature over the RAW body before trusting the
            // url/sha256 it carries. Nothing is downloaded on failure; the reason
            // is specific so the admin can tell a mis-signed registry from a
            // key-pinning problem.
            $reason = $verifier->reason($mint['body'], $mint['signature']);

            if ($reason !== null) {
                throw new AddonInstallException($reason);
            }

            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) $mint['body'], true) ?: [];
            $url = (string) ($payload['url'] ?? '');
            $sha256 = (string) ($payload['sha256'] ?? '');

            if ($url === '' || $sha256 === '') {
                throw new AddonInstallException('The registry response was incomplete.');
            }

            $source = new UrlSource($url, $sha256);
            // Match case-insensitively: the stored registry_id comes from the
            // addon manifest and $this->registryId from the catalog API, and the
            // page merges them the same way. A case-only difference on a
            // case-sensitive store (pgsql/sqlite) must still resolve to an update,
            // not fall through to a fresh install that aborts as "already installed".
            $existing = Addon::query()
                ->whereRaw('LOWER(registry_id) = ?', [Str::lower($this->registryId)])
                ->first();

            if ($existing instanceof Addon) {
                InstallProgress::set($this->registryId, 'updating', 60, 'Updating…');
                $registry->update(
                    $existing->getName(),
                    $source,
                    $this->runMigrations ? $this->migrationHook() : null,
                );
            } else {
                InstallProgress::set($this->registryId, 'installing', 60, 'Installing…');
                $this->freshInstall($registry, $source);
            }

            InstallProgress::set($this->registryId, 'done', 100, 'Installed.');
            $this->notify(true, 'Addon installed', sprintf('%s is ready.', $this->registryId));
        } catch (Throwable $throwable) {
            // Log the real exception (the notification body is otherwise the only
            // audit trail), then report failure through progress + the bell.
            report($throwable);
            InstallProgress::set($this->registryId, 'error', 100, $throwable->getMessage());
            $this->notify(false, 'Addon install failed', $throwable->getMessage());
        }
    }

    /**
     * Fresh install: place + register, then run migrations if requested. A
     * migration failure rolls back in dependency-safe order — drop the declared
     * tables while files/manifest still exist, delete the row, then remove files.
     */
    private function freshInstall(AddonRegistry $registry, UrlSource $source): void
    {
        $addon = $registry->install($source);

        if (!$this->runMigrations) {
            return;
        }

        // Snapshot the schema before migrations so rollback can drop ONLY the
        // tables this install actually created — never a pre-existing core table
        // a (publisher-authored) manifest might wrongly declare as its own.
        $tablesBefore = Schema::getTableListing();

        try {
            InstallProgress::set($this->registryId, 'migrating', 85, 'Running migrations…');
            $this->runAddonMigrations($addon);
        } catch (Throwable $throwable) {
            $this->rollbackFreshInstall($registry, $addon, $tablesBefore);

            throw new AddonInstallException(sprintf('Migration failed: %s', $throwable->getMessage()), (int) $throwable->getCode(), $throwable);
        }
    }

    /**
     * Roll back a failed fresh install in dependency-safe order: drop only the
     * tables the addon actually created (its declared tables intersected with
     * the tables that newly appeared), purge its migration records, delete the
     * row, then remove the files. Bounding the drop to newly-created tables
     * enforces the design's safety premise — a fresh install's tables did not
     * exist before — so a manifest naming a core table can never drop it.
     *
     * @param list<string> $tablesBefore table names present before migrations
     */
    private function rollbackFreshInstall(AddonRegistry $registry, Addon $addon, array $tablesBefore): void
    {
        $path = $addon->getPath();
        $declared = app(ManifestParser::class)->parse($path)?->tables ?? [];
        $created = array_diff(Schema::getTableListing(), $tablesBefore);
        $toDrop = array_values(array_intersect($declared, $created));

        $migrations = app(MigrationService::class);

        if ($toDrop !== []) {
            $migrations->dropAddonTables($toDrop);
        }

        $migrations->purgeAddonMigrationRecords($addon);

        // removeTables:false — we already dropped exactly the new tables above;
        // delete() only removes the row + asset links here.
        $registry->delete($addon->getName(), removeTables: false);
        File::deleteDirectory($path);
    }

    /**
     * Post-placement hook for updates: runs the new version's migrations inside
     * AddonRegistry::update()'s protected window, so a failure restores the
     * previous version (never dropping tables).
     *
     * @return Closure(Addon):void
     */
    private function migrationHook(): Closure
    {
        return function (Addon $addon): void {
            InstallProgress::set($this->registryId, 'migrating', 85, 'Running migrations…');
            $this->runAddonMigrations($addon);
        };
    }

    private function runAddonMigrations(Addon $addon): void
    {
        $path = $addon->getPath().'/database/migrations';

        if (!is_dir($path)) {
            return;
        }

        $exit = Artisan::call('migrate', [
            '--force'    => true,
            '--realpath' => true,
            '--path'     => [$path],
        ]);

        if ($exit !== 0) {
            throw new AddonInstallException(sprintf('Migrations exited with code %d: %s', $exit, trim(Artisan::output())));
        }
    }

    private function mintErrorMessage(int $status): string
    {
        return match ($status) {
            404     => 'This release is no longer available (it may have been yanked).',
            503     => 'This release is not ready to download yet — try again shortly.',
            default => sprintf('The registry could not mint a download (HTTP %d).', $status),
        };
    }

    private function notify(bool $success, string $title, string $body): void
    {
        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        $notification = Notification::make()->title($title)->body($body);
        $success ? $notification->success() : $notification->danger();
        $notification->sendToDatabase($user);
    }
}
