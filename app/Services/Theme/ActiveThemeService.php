<?php

declare(strict_types=1);

namespace App\Services\Theme;

use App\Models\ActiveThemePublication;
use App\Models\PublishedThemeRevision;

final class ActiveThemeService
{
    public function __construct(private readonly ThemeAssetService $assets) {}

    public function revision(string $themeName): ?PublishedThemeRevision
    {
        return ActiveThemePublication::query()
            ->with('revision')
            ->find($themeName)
            ?->revision;
    }

    /**
     * @return array{theme: string, custom: string|null}
     */
    public function stylesheetUrls(PublishedThemeRevision $revision): array
    {
        return [
            'theme' => $this->assets->url(
                $revision->theme_name,
                $revision->revision,
                'theme.css'
            ),
            'custom' => $revision->custom_css === null ? null : $this->assets->url(
                $revision->theme_name,
                $revision->revision,
                'custom.css'
            ),
        ];
    }
}
