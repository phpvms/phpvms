<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator-managed map layers for the maplibre map platform (design.md D9).
 *
 * `raster` and `vector` are OVERLAYS, drawn on top of the basemap — a WMS
 * endpoint is a raster layer whose `url_template` carries the query string
 * (e.g. a `{bbox-epsg-3857}` placeholder), not a distinct type.
 *
 * `basemap` is the third type and is NOT an overlay: it is the style the map
 * itself is built from, so it is excluded from the overlay list the package
 * hands to `applyLayers()` and resolved separately into
 * `MapConfigData.basemapLight`/`basemapDark`. A basemap is the one type that
 * needs TWO urls — `url_template` holds the light style, `url_template_dark`
 * the dark one — because the surface swaps style with the panel theme.
 *
 * Stored as strings rather than a native DB enum, matching this app's
 * existing convention of raw-string contract columns over enum round-trips
 * (see `2026_07_27_000500_phase_columns_take_raw_contract_strings.php`).
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('map_layers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'raster' | 'vector' | 'basemap'
            $table->text('url_template');

            // Only ever set on a `basemap` row: its dark-theme style URL,
            // where `url_template` holds the light one. Null for overlays,
            // which have a single tile template and no theme variant.
            $table->text('url_template_dark')->nullable();
            $table->string('attribution')->nullable();
            $table->unsignedTinyInteger('min_zoom')->default(0);
            $table->unsignedTinyInteger('max_zoom')->default(22);
            $table->float('opacity')->default(1);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('order')->default(0);

            // NOT a secret. Any tile key is referrer-restricted and public by
            // construction, because the browser is what fetches the tiles —
            // AdminPanelProvider already ships the OpenAIP key this way today.
            // Plain text; no encryption, no server-side proxy. See design.md D9.
            $table->string('api_key')->nullable();

            // Which surfaces this layer applies to; null means every surface.
            $table->json('surfaces')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_layers');
    }
};
