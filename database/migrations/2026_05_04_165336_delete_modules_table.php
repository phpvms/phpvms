<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // The legacy nwidart `modules` table only stored runtime active state for
        // a package that no longer exists. The new addon system seeds itself from
        // the filesystem (see create_addons_table), so there is nothing to carry
        // over — just drop the legacy table.
        Schema::dropIfExists('modules');
    }
};
