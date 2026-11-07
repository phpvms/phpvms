<?php

namespace App\Observers;

use App\Models\Setting;
use App\Services\SettingService;

/**
 * Class SettingObserver
 */
class SettingObserver
{
    public function creating(Setting $model): void
    {
        if (!empty($model->id)) {
            $model->id = Setting::formatKey($model->id);
        }
    }

    /**
     * Any Eloquent write to a settings row drops the cached value the
     * `setting()` helper keeps, so edits that bypass SettingService::store()
     * (tinker, seeders, data migrations working on model instances) are seen
     * on the next read. Bulk query-builder writes and raw SQL fire no model
     * events; the `migrate-data` command flushes the cache for those.
     */
    public function saved(Setting $model): void
    {
        app(SettingService::class)->forgetCached($model->id);
    }

    public function deleted(Setting $model): void
    {
        app(SettingService::class)->forgetCached($model->id);
    }
}
