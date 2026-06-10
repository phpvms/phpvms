<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // nwidart is gone; drop any registered Vacentral addon row instead of
        // calling the removed Module facade.
        if (Schema::hasTable('addons')) {
            DB::table('addons')
                ->where('name', 'Vacentral')
                ->orWhere('namespace', 'Modules\\Vacentral')
                ->delete();
        }
    }
};
