<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `theme_settings` table, which stores global and per-theme
 * configuration keyed by (theme, name). `type` is the PHP type of the saved
 * value (string, bool, int, float, array) and `content` its text form; an array
 * is stored as JSON. A consumer looks a value up by a name instead of growing a
 * new settings key for each need.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('theme_settings')) {
            return;
        }

        Schema::create('theme_settings', function (Blueprint $table): void {
            $table->string('theme', 64);
            $table->string('name');
            $table->string('type', 16)->default('string');
            $table->text('content')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->primary(['theme', 'name']);
            $table->index('theme');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
    }
};
