<?php

declare(strict_types=1);

use App\Addons\Support\ManifestParser;
use Illuminate\Support\Facades\File;

/**
 * Resolve the installed sample addon's directory, or null when there is none.
 *
 * The bundled modules/Sample was removed from the repo and the module that
 * replaced it is not tracked (modules/.gitignore ignores every addon
 * directory), so a fresh clone or a CI checkout has no sample addon at all.
 * The coverage that drives one is therefore only available to a working copy
 * that happens to have it installed — callers guard those tests with
 * ->skip(sampleAddonMissing(...), ...) rather than failing the suite.
 *
 * Matched on the manifest, never on the directory name: the addon engine reads
 * each addon's own module.json/composer.json and is directory-name agnostic, so
 * the sample may sit in "Sample", "phpvms-sample", or anything else.
 */
function sampleAddonPath(): ?string
{
    $base = config('addons.paths.base', base_path('modules'));

    if (!is_string($base) || !File::isDirectory($base)) {
        return null;
    }

    $parser = new ManifestParser();

    foreach (File::directories($base) as $directory) {
        if ($parser->parse($directory)?->registryId === 'phpvms/sample') {
            return $directory;
        }
    }

    return null;
}

/**
 * Whether no sample addon is installed on disk. See sampleAddonPath().
 */
function sampleAddonMissing(): bool
{
    return sampleAddonPath() === null;
}
