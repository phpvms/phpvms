<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `migrate` safe around the cache tables. Never touches the cache store.
 */
final class PrepareCacheTables implements Step
{
    private const string MIGRATION = '2025_01_13_163743_create_cache_table';

    public function step(): SetupStep
    {
        return SetupStep::CacheTables;
    }

    public function applies(StepContext $ctx): bool
    {
        if (!$this->recorded()) {
            return Schema::hasTable('cache') || Schema::hasTable('cache_locks');
        }

        return !Schema::hasTable('cache_locks');
    }

    public function run(StepContext $ctx): void
    {
        if (!$this->recorded()) {
            // Cache data is disposable; migrate recreates both tables with v8's columns.
            foreach (['cache', 'cache_locks'] as $table) {
                if (Schema::hasTable($table)) {
                    Schema::dropIfExists($table);
                    ($ctx->log)("Dropped table {$table}");
                }
            }

            return;
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
            ($ctx->log)('Created table cache_locks');
        }
    }

    private function recorded(): bool
    {
        return Schema::hasTable('migrations')
            && DB::table('migrations')->where('migration', self::MIGRATION)->exists();
    }
}
