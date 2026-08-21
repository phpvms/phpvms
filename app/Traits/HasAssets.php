<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Asset;

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
    /** The slot this model's assets live in; one of Asset::SLOT_*. */
    abstract public function assetSlot(): string;

    /** The URL of this model's asset, or null when there is none to link. */
    public function assetUrl(): ?string
    {
        return Asset::getUrl($this->assetSlot(), (string) $this->getKey());
    }
}
