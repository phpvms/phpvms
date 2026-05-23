<?php

use Illuminate\Database\Migrations\Migration;
use Nwidart\Modules\Facades\Module;

return new class() extends Migration
{
    public function up(): void
    {
        $module = Module::find('Vacentral');
        $module?->delete();
    }
};
