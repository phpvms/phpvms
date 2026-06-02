<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Normalize existing values so the cast to JSON can't fail.
        // Null/blank or non-decodable payloads become an empty JSON object.
        DB::table('notifications')->orderBy('id')->chunk(100, function ($notifications): void {
            foreach ($notifications as $notification) {
                $value = $notification->data;

                if (blank($value) || json_decode((string) $value) === null) {
                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update(['data' => '{}']);
                }
            }
        });

        // 2. Officially change the column type to JSON.
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
