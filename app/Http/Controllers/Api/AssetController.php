<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Features\Assets\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a PRIVATE asset's bytes.
 *
 * Public assets never reach here: they live on the public disk and
 * {@see Asset::url()} hands out their storage URL directly, because site
 * branding and airline marks are public information rendered on pages a
 * logged-out visitor sees. Routing those through PHP would add a hop and
 * protect nothing.
 *
 * So this endpoint exists for the assets that are NOT public — an uploaded
 * sound, a paintkit — which have no URL of their own and are reachable only
 * through an authenticated request.
 */
final class AssetController extends Controller
{
    public function __invoke(Request $request, Asset $asset): StreamedResponse
    {
        // A public asset has a storage URL; serving it here as well would give
        // the same bytes two addresses and two cache lifetimes.
        abort_if($asset->is_public, 404);

        $disk = Storage::disk($asset->diskName());
        abort_unless($disk->exists($asset->path), 404);

        $response = new StreamedResponse(function () use ($disk, $asset): void {
            $stream = $disk->readStream($asset->path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $asset->content_type,
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

        // Never a shared cache: the URL is identical for every caller and only
        // their credentials decide access.
        $response->setPrivate();

        // Turns the response into a bodiless 304 when the client's ETag still
        // matches. Safe on a StreamedResponse — setNotModified() calls
        // setContent(null), which marks it streamed so the callback above never
        // runs (vendor/symfony/http-foundation/StreamedResponse.php:135-144).
        $response->isNotModified($request);

        return $response;
    }
}
