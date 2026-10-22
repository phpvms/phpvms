<?php

declare(strict_types=1);

namespace App\Traits;

use App\Features\Assets\AssetService;
use App\Models\Asset;
use Illuminate\Support\Collection;

/**
 * Gives a model the asset keyed by its own primary key, in the slot it
 * declares.
 *
 * The trait earns its place only because the model supplies the key. Anything
 * global — a slot and a key that come from neither — is {@see Asset::getUrl()},
 * which needs no model at all.
 */
trait HasAssets
{
    /**
     * Set by {@see preloadAssetUrls()}; when true, {@see assetUrl()} returns
     * this instead of running its own query.
     */
    private bool $assetUrlPreloaded = false;

    private ?string $preloadedAssetUrl = null;

    /** The slot this model's assets live in; one of Asset::SLOT_*. */
    abstract public function assetSlot(): string;

    /**
     * The record's file IS the asset — deleting it and leaving the asset
     * behind orphans a row nothing owns, and the bytes it points at.
     *
     * Hooked on `deleted`, not `forceDeleted`: the latter only exists on a
     * model using SoftDeletes, and this trait has to work on one that
     * doesn't. `deleted` fires either way, so the guard below is what tells
     * the two apart.
     */
    protected static function bootHasAssets(): void
    {
        static::deleted(function (self $model): void {
            // A soft delete leaves the record around to be restored, and a
            // restored award/rank/bundle should still have its badge — so
            // only a genuine, irreversible deletion earns cleanup here.
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            // Deleted through the model, not the query builder, so Asset's
            // own `deleted` hook removes the file too.
            app(AssetService::class)->find($model->assetSlot(), (string) $model->getKey())?->delete();
        });
    }

    /** The URL of this model's asset, or null when there is none to link. */
    public function assetUrl(): ?string
    {
        if ($this->assetUrlPreloaded) {
            return $this->preloadedAssetUrl;
        }

        return Asset::getUrl($this->assetSlot(), (string) $this->getKey());
    }

    /**
     * Resolve every URL for `$models` in one query instead of one per model,
     * then stash each result so {@see assetUrl()} returns it directly.
     *
     * All models must share a slot — this is one query per call, keyed on
     * whatever the first model's {@see assetSlot()} returns.
     *
     * @param iterable<int, static> $models
     */
    public static function preloadAssetUrls(iterable $models): void
    {
        $models = $models instanceof Collection ? $models : new Collection($models);

        if ($models->isEmpty()) {
            return;
        }

        $slot = $models->first()->assetSlot();
        $keys = $models->map(fn (self $model): string => (string) $model->getKey())->all();
        $urls = app(AssetService::class)->urlsFor($slot, $keys);

        foreach ($models as $model) {
            $model->preloadedAssetUrl = $urls[(string) $model->getKey()] ?? null;
            $model->assetUrlPreloaded = true;
        }
    }
}
