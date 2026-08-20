<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Features\Assets\AssetStream;
use App\Models\Asset;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an asset's bytes to a caller carrying `assets:read`.
 *
 * It exists for assets whose disk declares no URL — an uploaded sound, a
 * paintkit — which are reachable no other way. It does not refuse the ones that
 * DO have a URL: whether an asset is served here is not a property of the row,
 * it is this route's own authorization, and an asset on a linkable disk is
 * simply also fetchable at its own address.
 */
final class AssetController extends Controller
{
    public function __invoke(Request $request, Asset $asset): StreamedResponse
    {
        return AssetStream::response($asset, $request);
    }
}
