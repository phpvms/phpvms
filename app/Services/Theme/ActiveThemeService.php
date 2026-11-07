<?php

declare(strict_types=1);

namespace App\Services\Theme;

use App\Models\ActiveThemePublication;
use App\Models\PublishedThemeRevision;

final class ActiveThemeService
{
    /** @var array<string, PublishedThemeRevision|null> */
    private array $revisions = [];

    public function __construct(
        private readonly ThemeAssetService $assets,
        private readonly ThemeDocumentNormalizer $normalizer,
        private readonly ThemePublicationService $publications,
    ) {}

    /**
     * Memoised per theme for the life of the instance, which is bound scoped
     * so each request resolves the revision once.
     */
    public function revision(string $themeName): ?PublishedThemeRevision
    {
        if (!array_key_exists($themeName, $this->revisions)) {
            $this->revisions[$themeName] = $this->resolveRevision($themeName);
        }

        return $this->revisions[$themeName];
    }

    /**
     * Drop the memoised revision after a publish or rollback so the same
     * request sees the change.
     */
    public function forget(string $themeName): void
    {
        unset($this->revisions[$themeName]);
    }

    private function resolveRevision(string $themeName): ?PublishedThemeRevision
    {
        $revision = ActiveThemePublication::query()
            ->with('revision')
            ->find($themeName)
            ?->revision;

        if (!$this->usesSourceDefaults($themeName)) {
            return $revision;
        }

        $document = $this->normalizer->defaults();
        $preview = $this->publications->preview($document);
        $customCss = $revision?->custom_css;
        $sourceRevision = $this->publications->contentRevision(
            $preview['document'],
            $customCss,
            $preview['css'],
        );

        return $revision?->revision === $sourceRevision
            ? $revision
            : $this->publications->publish($themeName, $document, $customCss);
    }

    private function usesSourceDefaults(string $themeName): bool
    {
        return $themeName === 'skylight'
            && !app()->environment(['production', 'testing'])
            && is_file(public_path('build/skylight/hot'));
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
