<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE notifications MODIFY COLUMN data JSON NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE notifications MODIFY COLUMN data TEXT NOT NULL');
        }
    }
};
