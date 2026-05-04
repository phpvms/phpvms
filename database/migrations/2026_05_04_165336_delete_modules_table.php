<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modules')) {
            $modules = DB::table('modules')->get();
            foreach ($modules as $module) {
                app(ModuleService::class)->updateModule($module->name, $module->enabled);
            }
        }

        Schema::dropIfExists('modules');
    }
};
