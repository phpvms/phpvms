<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Controller;
use App\Models\PublishedThemeRevision;
use App\Services\Theme\ThemeAssetService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ThemeAssetController extends Controller
{
    public function __invoke(
        string $themeName,
        string $revision,
        string $asset,
        ThemeAssetService $assets,
    ): StreamedResponse {
        $published = PublishedThemeRevision::query()
            ->where('theme_name', $themeName)
            ->where('revision', $revision)
            ->firstOrFail();

        abort_if($asset === 'custom.css' && $published->custom_css === null, 404);
        abort_unless($assets->exists($themeName, $revision, $asset), 404);
        $stream = $assets->readStream($themeName, $revision, $asset);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'  => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
