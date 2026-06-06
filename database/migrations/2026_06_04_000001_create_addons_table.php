<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the addons registry table (D-06) and seeds bundled modules (D-08).
     */
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table): void {
            $table->id();
            $table->string('registry_id')->nullable()->unique();
            $table->string('type')->default('module');
            $table->string('version')->nullable();
            $table->string('namespace');
            $table->string('path');
            $table->boolean('enabled')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });

        // D-08: Seed bundled modules only when table is empty (idempotency guard).
        // Uses DB::table()->insert() (not the Addon model) so the migration stays standalone.
        if (DB::table('addons')->count() === 0) {
            $modulesPath = base_path('modules');
            $now = now();

            foreach (File::directories($modulesPath) as $directory) {
                $moduleJsonPath = $directory.'/module.json';

                if (!File::exists($moduleJsonPath)) {
                    continue;
                }

                /** @var array{} $manifest */
                $manifest = json_decode(File::get($moduleJsonPath), true);

                // D-15: skip invalid JSON — a single bad bundled manifest must not break migrations.
                if (!is_array($manifest)) {
                    continue;
                }

                // Read composer.json once for both namespace and version derivation.
                $composerPath = $directory.'/composer.json';
                $composer = null;
                if (File::exists($composerPath)) {
                    $composerDecoded = json_decode(File::get($composerPath), true);
                    $composer = is_array($composerDecoded) ? $composerDecoded : null;
                }

                // D-07: namespace from composer.json autoload.psr-4 first key; fallback to nwidart convention.
                $namespace = null;
                if ($composer !== null && isset($composer['autoload']['psr-4'])) {
                    $psr4Keys = array_keys($composer['autoload']['psr-4']);
                    if ($psr4Keys !== []) {
                        $namespace = rtrim((string) $psr4Keys[0], '\\');
                    }
                }

                if ($namespace === null) {
                    $namespace = 'Modules\\'.basename((string) $directory);
                }

                // D-07: version from module.json first, then composer.json, then null.
                $version = $manifest['version'] ?? ($composer['version'] ?? null);

                // Declare them as legacy if no registry_id is present.
                $registry_id = $manifest['registry_id'] ?? 'legacy/'.$manifest['alias'];

                // T-01-03: path is sourced only from File::directories() output — never from manifest.
                // T-01-02: bound parameters via query-builder insert() — no string concatenation.
                DB::table('addons')->insert([
                    'registry_id'  => $registry_id,
                    'type'         => $manifest['type'] ?? 'module',
                    'version'      => $version,
                    'namespace'    => $namespace,
                    'path'         => $directory,
                    'enabled'      => true,
                    'installed_at' => $now,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
