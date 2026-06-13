<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Data migration: backfill the addons.name column for rows seeded before
     * `name` was populated. The column was added nullable and the original
     * create_addons_table seeder left it unset, so pre-existing installs carry
     * name = null.
     *
     * Derives the display name from each addon's module.json `name`, falling back
     * to the directory basename, then the trailing namespace segment — mirroring
     * ManifestParser / the create_addons_table seeder. Idempotent: only rows whose
     * name is null or blank are touched, so operator-set names are preserved.
     *
     * Guarded on the name column existing so it is a safe no-op on a schema that
     * has not yet gained the column.
     */
    public function up(): void
    {
        if (!Schema::hasTable('addons') || !Schema::hasColumn('addons', 'name')) {
            return;
        }

        DB::table('addons')
            ->where(function ($query): void {
                $query->whereNull('name')->orWhere('name', '');
            })
            ->get(['id', 'path', 'namespace'])
            ->each(function (object $addon): void {
                DB::table('addons')
                    ->where('id', $addon->id)
                    ->update(['name' => $this->resolveName((string) $addon->path, (string) $addon->namespace)]);
            });
    }

    /**
     * Resolve a display name: module.json `name` → directory basename → trailing
     * namespace segment.
     */
    private function resolveName(string $path, string $namespace): string
    {
        $manifestPath = $path.'/module.json';

        if (File::exists($manifestPath)) {
            $data = json_decode(File::get($manifestPath), true);

            if (is_array($data) && isset($data['name']) && trim((string) $data['name']) !== '') {
                return (string) $data['name'];
            }
        }

        if ($path !== '') {
            return basename($path);
        }

        $segments = explode('\\', $namespace);

        return end($segments);
    }
};
