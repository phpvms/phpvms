<?php

declare(strict_types=1);

use App\Support\Utils;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce uniqueness of `users.api_key`.
 *
 * The column has always been a plain (non-unique) index, so a lookup by key
 * (ApiAuth and the api_key OAuth grant) could in principle match more than one
 * user. Regenerate any pre-existing duplicates, then swap the index for a unique
 * one so a key resolves to exactly one user at the database level.
 */
return new class() extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('users')
            ->select('api_key')
            ->whereNotNull('api_key')
            ->groupBy('api_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('api_key');

        foreach ($duplicates as $apiKey) {
            // Keep the lowest id, regenerate a fresh key for the rest.
            $ids = DB::table('users')->where('api_key', $apiKey)->orderBy('id')->pluck('id');

            foreach ($ids->slice(1) as $id) {
                DB::table('users')->where('id', $id)->update(['api_key' => Utils::generateApiKey()]);
            }
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['api_key']);
            $table->unique('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['api_key']);
            $table->index('api_key');
        });
    }
};
