<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Settings;
use Throwable;

/**
 * Saves typed values to several settings classes in one transaction.
 *
 * `Settings::save()` writes the package cache as soon as it runs, even inside a
 * transaction, so when the transaction rolls back the cache (and the request's
 * instances) would keep values that never committed. On failure every instance
 * touched is reloaded from its table, which also rewrites its cache entry.
 */
final readonly class SettingsWriter
{
    public function __construct(private SettingsCacheRefresher $refresher) {}

    /**
     * @param array<class-string<Settings>, array<string, mixed>> $valuesByClass typed values by property name
     */
    public function write(array $valuesByClass): void
    {
        $touched = [];

        try {
            DB::transaction(function () use ($valuesByClass, &$touched): void {
                foreach ($valuesByClass as $class => $values) {
                    $touched[] = $settings = app($class);
                    $settings->fill($values)->save();
                }
            });
        } catch (Throwable $throwable) {
            foreach ($touched as $settings) {
                $this->refresher->refresh($settings);
            }

            throw $throwable;
        }
    }
}
