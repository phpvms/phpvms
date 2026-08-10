<?php

declare(strict_types=1);

namespace App\Services\Theme;

use App\Models\ActiveThemePublication;
use App\Models\PublishedThemeRevision;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

final class ThemePublicationService
{
    public function __construct(
        private readonly ThemeDocumentNormalizer $normalizer,
        private readonly ThemeCssRenderer $renderer,
        private readonly ThemeAssetService $assets,
    ) {}

    /**
     * @return array{
     *   document: array<string, mixed>,
     *   diagnostics: array{},
     *   css: string,
     *   resolvedInput: array{components: array<string, mixed>, phpvms: array<string, mixed>},
     *   targets: array{routes: list<string>, components: list<string>}
     * }
     */
    public function preview(string|array $input): array
    {
        $document = is_string($input)
            ? $this->normalizer->normalizeJson($input)
            : $this->normalizer->normalize($input);

        return [
            'document'      => $document,
            'diagnostics'   => [],
            'css'           => $this->renderer->render($document),
            'resolvedInput' => [
                'components' => $document['nuxtUi']['components'],
                'phpvms'     => $document['phpvms'],
            ],
            'targets' => [
                'routes'     => ['dashboard', 'profile', 'pirep-detail'],
                'components' => ['button', 'input', 'dashboard-toolbar', 'pirep-summary'],
            ],
        ];
    }

    public function publish(string $themeName, string|array $input, ?string $customCss = null): PublishedThemeRevision
    {
        $preview = $this->preview($input);
        $this->validateCustomCss($customCss);

        $document = $preview['document'];
        $themeCss = $preview['css'];
        $revision = $this->contentRevision($document, $customCss, $themeCss);

        return $this->lock($themeName, function () use (
            $themeName,
            $document,
            $customCss,
            $themeCss,
            $revision,
        ): PublishedThemeRevision {
            $this->assets->write($themeName, $revision, 'theme.css', $themeCss);
            if ($customCss !== null) {
                $this->assets->write($themeName, $revision, 'custom.css', $customCss);
            }

            return DB::transaction(function () use ($themeName, $document, $customCss, $revision): PublishedThemeRevision {
                $published = PublishedThemeRevision::query()->firstOrCreate(
                    [
                        'theme_name' => $themeName,
                        'revision'   => $revision,
                    ],
                    [
                        'schema_version' => $document['version'],
                        'document'       => $document,
                        'custom_css'     => $customCss,
                        'published_at'   => now(),
                    ],
                );

                $active = ActiveThemePublication::query()
                    ->lockForUpdate()
                    ->find($themeName) ?? new ActiveThemePublication(['theme_name' => $themeName]);
                $active->published_theme_revision_id = $published->id;
                $active->save();

                return $published;
            });
        });
    }

    public function rollback(string $themeName, PublishedThemeRevision $revision): PublishedThemeRevision
    {
        if ($revision->theme_name !== $themeName) {
            throw ValidationException::withMessages([
                'revision' => ['The revision does not belong to this rendered theme.'],
            ]);
        }

        return $this->lock($themeName, function () use ($themeName, $revision): PublishedThemeRevision {
            if (!$this->assets->exists($themeName, $revision->revision, 'theme.css')) {
                throw new LogicException('The selected theme revision is incomplete.');
            }

            if ($revision->custom_css !== null
                && !$this->assets->exists($themeName, $revision->revision, 'custom.css')) {
                throw new LogicException('The selected theme revision is incomplete.');
            }

            DB::transaction(function () use ($themeName, $revision): void {
                $active = ActiveThemePublication::query()
                    ->lockForUpdate()
                    ->find($themeName) ?? new ActiveThemePublication(['theme_name' => $themeName]);
                $active->published_theme_revision_id = $revision->id;
                $active->save();
            });

            return $revision;
        });
    }

    /**
     * @return Collection<int, PublishedThemeRevision>
     */
    public function history(string $themeName): Collection
    {
        return PublishedThemeRevision::query()
            ->where('theme_name', $themeName)
            ->orderByDesc('published_at')
            ->get();
    }

    /**
     * @param array<string, mixed> $document
     */
    public function contentRevision(array $document, ?string $customCss, string $themeCss): string
    {
        $canonical = json_encode($this->sortKeys($document), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $customCssContent = $customCss === null ? 'absent' : "present\0{$customCss}";

        return hash('sha256', $canonical."\0theme.css\0{$themeCss}\0custom.css\0".$customCssContent);
    }

    private function validateCustomCss(?string $customCss): void
    {
        if ($customCss !== null && strlen($customCss) > config('themes.custom_css_max')) {
            throw ValidationException::withMessages([
                'customCss' => ['The custom CSS exceeds the maximum allowed size.'],
            ]);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T $callback
     * @return T
     */
    private function lock(string $themeName, callable $callback): mixed
    {
        return Cache::lock(
            'theme-publication:'.hash('sha256', $themeName),
            (int) config('themes.publish_lock.seconds'),
        )->block((int) config('themes.publish_lock.wait'), $callback);
    }

    /**
     * @param  array<mixed> $value
     * @return array<mixed>
     *
     * @throws JsonException
     */
    private function sortKeys(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortKeys($item);
            }
        }

        return $value;
    }
}
