<?php

declare(strict_types=1);

namespace App\Services\Theme;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use LogicException;

final class ThemeAssetService
{
    public function write(string $themeName, string $revision, string $asset, string $contents): void
    {
        $path = $this->path($themeName, $revision, $asset);
        $disk = $this->disk();

        if ($disk->exists($path)) {
            if ($disk->get($path) !== $contents) {
                throw new LogicException("Theme asset already exists with different content: {$path}");
            }

            return;
        }

        if (!$disk->put($path, $contents) || $disk->get($path) !== $contents) {
            throw new LogicException("Unable to publish theme asset: {$path}");
        }
    }

    public function exists(string $themeName, string $revision, string $asset): bool
    {
        return $this->disk()->exists($this->path($themeName, $revision, $asset));
    }

    /**
     * @return resource|null
     */
    public function readStream(string $themeName, string $revision, string $asset)
    {
        return $this->disk()->readStream($this->path($themeName, $revision, $asset));
    }

    public function url(string $themeName, string $revision, string $asset): string
    {
        if (config('themes.asset_delivery') === 'route') {
            return route('theme-assets.show', compact('themeName', 'revision', 'asset'));
        }

        return $this->disk()->url($this->path($themeName, $revision, $asset));
    }

    public function path(string $themeName, string $revision, string $asset): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $themeName)) {
            throw new LogicException('Invalid rendered theme name');
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $revision)) {
            throw new LogicException('Invalid theme revision');
        }

        if (!in_array($asset, ['theme.css', 'custom.css'], true)) {
            throw new LogicException('Invalid theme asset');
        }

        return "{$themeName}/{$revision}/{$asset}";
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.theme_assets'));
    }
}
