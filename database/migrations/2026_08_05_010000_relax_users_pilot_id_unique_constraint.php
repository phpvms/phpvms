<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusing a deleted pilot's ID (pilots.id_reuse_deleted) intentionally assigns the
 * same pilot_id to both the soft-deleted row and a new active user. Uniqueness among
 * active users is already enforced in App\Services\UserService, so a DB-level unique
 * constraint is no longer correct here; keep an index for lookup performance instead.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['pilot_id']);
            $table->index('pilot_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['pilot_id']);
            $table->unique('pilot_id');
        });
    }
};
