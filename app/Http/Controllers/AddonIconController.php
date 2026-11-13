<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Controller;
use App\Features\Addons\AddonRegistry;
use App\Features\Addons\Support\AddonIcon;
use App\Features\Addons\Support\ManifestParser;
use App\Models\Addon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Serves the local icon an addon declares in its manifest. Only that one file
 * is reachable, and only when it is an image inside the addon folder.
 */
final class AddonIconController extends Controller
{
    public function __invoke(string $addon, AddonRegistry $registry): BinaryFileResponse
    {
        $row = $registry->find($addon);
        abort_if(!$row instanceof Addon, 404);

        try {
            $icon = new ManifestParser()->parse($row->getPath())?->icon;
        } catch (Throwable) {
            abort(404);
        }

        $file = AddonIcon::file($row->getPath(), $icon);
        abort_if($file === null, 404);

        return response()->file($file, [
            'Cache-Control' => 'private, max-age=3600',
            // An SVG opened directly must not run script on this origin.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options'  => 'nosniff',
        ]);
    }
}
