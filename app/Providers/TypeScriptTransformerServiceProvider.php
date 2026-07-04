<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Generates TypeScript types the skylight SPA (and addon widgets) consume,
 * straight from PHP DTOs — run `php artisan typescript:transform` to regenerate.
 *
 * Package note: spatie/typescript-transformer v3 is a rewrite that configures
 * itself through THIS provider (a fluent factory) rather than a published
 * `config/typescript-transformer.php` file. Only classes carrying the
 * #[\Spatie\TypeScriptTransformer\Attributes\TypeScript] attribute are emitted
 * by AttributedClassTransformer, so the generated surface stays intentionally
 * small: annotate a `Data` DTO to opt it in.
 *
 * The laravel-data ↔ transformer bridge (Spatie\LaravelData\...\DataTypeScriptTransformer)
 * still targets the transformer v2 API and is NOT compatible with v3, so it is
 * intentionally not registered here. For plain `Data` classes with scalar/array
 * properties (like SamplePingData) the generic AttributedClassTransformer
 * reflects the public promoted properties and produces the correct shape.
 */
class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            // Only #[TypeScript]-annotated classes are emitted (opt-in surface).
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            // Scope narrowly. EnumTransformer emits EVERY enum in a scanned dir
            // (no attribute needed), so we deliberately avoid app_path() as a
            // whole and the large app/Support/Dto/SimBriefOfp tree. Add the
            // specific dirs that hold opt-in DTOs instead.
            ->transformDirectories(
                base_path('modules/phpvms-sample-vue-widget/Http/Data'),
                app_path('Support/Dto/PhpvmsApi'),
                // Frontend page DTOs (the SPA projection returned by controllers).
                // Holds only #[TypeScript] Data classes (no enums), so scanning it
                // is safe w.r.t. the EnumTransformer caveat above.
                app_path('Http/Data'),
            )
            // Final file: outputDirectory + writer path.
            ->outputDirectory(resource_path('js/apps/frontend-ui/apps/spa/types'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            // No manifest json cluttering the SPA types dir.
            ->withoutManifest();
        // No formatter: PrettierFormatter shells out to `npx prettier`, an
        // avoidable runtime dependency. The generated .d.ts is valid TS as-is.
    }
}
