<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('roles', static function (Blueprint $table) {
            $table->boolean('read_only')->default(false);
        });

        // Set the two main roles as read-only
        DB::table('roles')
            ->whereIn('name', ['admin', 'user'])
            ->update(['read_only' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', static function (Blueprint $table) {
            $table->dropColumn('read_only');
        });
    }
};
