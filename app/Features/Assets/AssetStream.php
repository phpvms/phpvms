<?php

declare(strict_types=1);

namespace App\Features\Assets;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the byte response for a private asset.
 *
 * Extracted because more than one route serves these — core's own API endpoint
 * and a module's, which authenticate differently but must hand back
 * byte-identical responses. The caching rules below are subtle enough that two
 * copies would drift.
 */
final class AssetStream
{
    /**
     * Stream $asset's bytes, answering a matching `If-None-Match` with a 304.
     *
     * Callers are responsible for deciding whether this request may see the
     * asset at all; by the time it gets here, it may.
     */
    public static function response(Asset $asset, Request $request): StreamedResponse
    {
        // A link owns no bytes and its `path` is a URL, so there is nothing here
        // to stream and no disk to ask. 404 rather than resolving a disk named
        // `url`, which does not exist and would throw.
        abort_if($asset->isLink(), 404);

        $disk = Storage::disk($asset->diskName());
        abort_unless($disk->exists($asset->path), 404);

        $response = new StreamedResponse(function () use ($disk, $asset): void {
            $stream = $disk->readStream($asset->path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            // `content_type` is nullable — a stored asset always has one, but
            // the column allows null for links, and a null here is a TypeError
            // in the header bag rather than a missing header.
            'Content-Type' => $asset->content_type ?? 'application/octet-stream',
            // Read off the disk rather than the row: a size that disagrees with
            // the bytes truncates the response at the client.
            'Content-Length' => (string) $disk->size($asset->path),
        ]);

        // Not `immutable`, and not a long max-age. The URL is keyed on the
        // asset id, and replacing an asset keeps that id — so the same URL can
        // legitimately return different bytes and a cached copy must be
        // revalidated. `last_update` is a hash of the content, which is exactly
        // an ETag, so revalidation costs a 304 and no body.
        $response->setEtag($asset->last_update);
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');

        // `private` does NOT stop the client caching — it stops SHARED caches
        // (a proxy, a CDN) from keeping a copy, which matters because the URL
        // is identical for every caller and only their credentials decide
        // access. Without it, one pilot's paintkit could be handed to the next
        // request from an intermediary.
        //
        // The client still caches, and still has to revalidate every time
        // because of max-age=0 + must-revalidate above. That is the intent: the
        // check costs one conditional request and the 304 carries no body, so a
        // cached asset is confirmed for free and a changed one is noticed
        // immediately.
        $response->setPrivate();

        // Turns the response into a bodiless 304 when the client's ETag still
        // matches. Safe on a StreamedResponse — setNotModified() calls
        // setContent(null), which marks it streamed so the callback above never
        // runs (vendor/symfony/http-foundation/StreamedResponse.php:135-144).
        $response->isNotModified($request);

        return $response;
    }
}
