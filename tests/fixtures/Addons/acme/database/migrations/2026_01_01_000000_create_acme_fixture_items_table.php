<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixture migration for the acme test addon. Proves the base addon
 * ServiceProvider registers `{root}/database/migrations` so `artisan migrate`
 * runs an enabled addon's migrations.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('acme_fixture_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acme_fixture_items');
    }
};
