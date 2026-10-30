<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * Renders airside's Vite entries for the `@airsideVite` directive.
 *
 * The theme builds into its own `public/build` (served from
 * `/storage/themes/airside/build`) with its own hot file. It works on a clone
 * of the container's Vite instance, so the build directory and hot file of
 * the instance every other `@vite` call uses are never changed (a long-running
 * Octane worker would otherwise carry them into the next request), and a test
 * double bound for `withoutVite()` is still honoured.
 */
final readonly class AirsideVite
{
    public const string BUILD_DIRECTORY = 'storage/themes/airside/build';

    public function __construct(private string $hotFile) {}

    /**
     * @param string|list<string> $entrypoints Manifest keys, relative to the theme root
     */
    public function __invoke(string|array $entrypoints): HtmlString
    {
        $vite = clone app(Vite::class);

        return $vite
            ->useBuildDirectory(self::BUILD_DIRECTORY)
            ->useHotFile($this->hotFile)($entrypoints);
    }
}
