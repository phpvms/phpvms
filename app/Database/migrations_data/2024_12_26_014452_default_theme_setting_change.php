<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

        // Check if the default theme exists physically, and a beta directory exists delete it.
        if (file_exists(resource_path('views/layouts/default')) && file_exists(resource_path('views/layouts/beta'))) {
            File::deleteDirectory(resource_path('views/layouts/default'));
        }

        // check if the default theme is being extended by any other theme. If so, change the extension to beta in that theme's theme.json.
        $themes = File::directories(resource_path('views/layouts'));
        foreach ($themes as $theme) {
            $themeJson = json_decode(File::get($theme.'/theme.json'));
            if (!property_exists($themeJson, 'extends') || $themeJson->extends === 'default') {
                $themeJson->extends = 'beta';
                File::put($theme.'/theme.json', json_encode($themeJson, JSON_PRETTY_PRINT));
            }
        }

        // Clear the app and view cache
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
