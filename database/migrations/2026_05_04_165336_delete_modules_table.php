<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nwidart\Modules\Facades\Module;

return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modules')) {
            $modules = DB::table('modules')->get();
            foreach ($modules as $module) {
                Module::find($module->name)?->setActive((bool) $module->enabled);
            }
        }

        Schema::dropIfExists('modules');
    }
};
