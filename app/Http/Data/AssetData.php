<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Asset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The wire shape of an asset.
 *
 * The `Asset` model is deliberately not serialized directly: it is naked — no
 * `$hidden`, no `$appends`, no `toArray()` — so handing one to a response would
 * leak its storage `path`, its `source` and its `updated_by`, and would omit the
 * one field every consumer actually needs, the URL. This is the shape endpoints
 * return.
 *
 * @see Asset
 */
#[TypeScript]
final class AssetData extends Data
{
    public function __construct(
        public string $id,
        public string $key,
        public string $slot,
        public string $type,
        public string $content_type,
        public string $url,
        public string $last_update,
    ) {}

    /**
     * @param string|null $url overrides where the bytes are fetched from.
     *
     * A private asset's default URL is core's own authenticated endpoint, which
     * expects a core API credential and the `assets:read` scope. A caller
     * serving a different audience — the ACARS contract, say, whose clients
     * hold a plugin token instead — passes its own route here. Same bytes,
     * different door, and the caller has to send consumers to the one they can
     * open.
     */
    public static function fromModel(Asset $asset, ?string $url = null): self
    {
        return new self(
            id: (string) $asset->id,
            key: (string) $asset->key,
            slot: (string) $asset->slot,
            type: (string) $asset->type,
            content_type: (string) $asset->content_type,
            url: $url ?? $asset->url(),
            last_update: (string) $asset->last_update,
        );
    }

    /**
     * An asset-shaped record for bytes we do NOT host.
     *
     * Only for values that predate the assets table and are still a plain
     * external URL — an airline mark someone typed in or imported. There is no
     * row behind it, hence the empty id, and the URL is its own change stamp
     * because equality on the URL is the only change signal an external link
     * offers.
     */
    public static function external(string $slot, string $key, string $url, string $type = 'image'): self
    {
        return new self(
            id: '',
            key: $key,
            slot: $slot,
            type: $type,
            content_type: '',
            url: $url,
            last_update: $url,
        );
    }
}
