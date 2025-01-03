<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check in the DB if the general_theme setting exists. If so, check if the value is "default". If so, change it to "beta".
        if (Schema::hasTable('settings')) {
            $theme = DB::table('settings')->where('key', 'general_theme')->first();
            if ($theme && $theme->value === 'default') {
                DB::table('settings')->where('key', 'general_theme')->update(['value' => 'beta']);
            }
        }

        // Check if the default theme exists physically. If it does, delete it.
        if (file_exists(resource_path('views/layouts/default'))) {
            File::deleteDirectory(resource_path('views/layouts/default'));
        }
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
