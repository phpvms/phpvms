<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('published_theme_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('theme_name');
            $table->unsignedSmallInteger('schema_version');
            $table->json('document');
            $table->longText('custom_css')->nullable();
            $table->char('revision', 64);
            $table->timestamp('published_at');

            $table->unique(['theme_name', 'revision']);
        });

        Schema::create('active_theme_publications', function (Blueprint $table): void {
            $table->string('theme_name')->primary();
            $table->foreignId('published_theme_revision_id')
                ->constrained('published_theme_revisions')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_theme_publications');
        Schema::dropIfExists('published_theme_revisions');
    }
};
