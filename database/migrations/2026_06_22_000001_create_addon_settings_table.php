<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Create the per-addon settings table.
     *
     * Mirrors the typed columns of the core `settings` table but scopes every
     * row to an owning addon (`addon_id`). The `alias` column is denormalized
     * from the addon manifest at sync time for fast helper lookups and display;
     * `addon_id` is the authoritative scope. Unique on (addon_id, key).
     */
    public function up(): void
    {
        Schema::create('addon_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
            $table->string('alias')->nullable()->index();
            $table->unsignedInteger('order')->default(99);
            $table->string('key');
            $table->string('name');
            $table->string('value')->nullable();
            $table->string('default')->nullable();
            $table->string('group')->nullable();
            $table->string('type')->nullable();
            $table->text('options')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['addon_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_settings');
    }
};
