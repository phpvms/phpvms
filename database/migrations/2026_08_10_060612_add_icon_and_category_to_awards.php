<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table): void {
            // Blade-icons name (e.g. "tabler-award"), so rendering is @svg($award->icon).
            $table->string('icon')->nullable()->after('image_url');

            // Free-form label. The presets in Award::CATEGORIES are a starting
            // set, not a closed list — admins add their own from the picker.
            $table->string('category', 64)->nullable()->after('icon')->index();
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table): void {
            $table->dropIndex(['category']);
            $table->dropColumn(['icon', 'category']);
        });
    }
};
