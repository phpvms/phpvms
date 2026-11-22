<?php

declare(strict_types=1);

use App\Features\Addons\Support\AddonRoots;
use App\Features\Addons\Support\ManifestParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: every bundled addon has an enabled, bundled row.
 *
 * Bundled addons cannot be disabled from the panel, so forcing `enabled` loses
 * no operator choice. Row values come from each addon's own manifest.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addons')) {
            return;
        }

        $parser = app(ManifestParser::class);
        $now = now();

        foreach (AddonRoots::bundled() as $name) {
            $path = AddonRoots::bundledSource().'/'.$name;
            $manifest = is_dir($path) ? $parser->parse($path) : null;

            if ($manifest === null) {
                continue;
            }

            $values = [
                'name'        => $manifest->name,
                'registry_id' => $manifest->registryId,
                'type'        => $manifest->type,
                'version'     => $manifest->version,
                'namespace'   => $manifest->namespace,
                'path'        => realpath($path) ?: $path,
                'enabled'     => true,
                'bundled'     => true,
                'updated_at'  => $now,
            ];

            // registry_id is the identity; namespace is unique, so a legacy row matched on it is reused.
            $existing = DB::table('addons')
                ->where('registry_id', $manifest->registryId)
                ->orWhere('namespace', $manifest->namespace)
                ->value('id');

            if ($existing !== null) {
                DB::table('addons')->where('id', $existing)->update($values);
            } else {
                DB::table('addons')->insert($values + ['installed_at' => $now, 'created_at' => $now]);
            }
        }
    }
};
