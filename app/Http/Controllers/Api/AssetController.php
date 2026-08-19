<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Features\Assets\AssetStream;
use App\Models\Asset;
use Illuminate\Http\Request;
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

        return AssetStream::response($asset, $request);
    }
}
