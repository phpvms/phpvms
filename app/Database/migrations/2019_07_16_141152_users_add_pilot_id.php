<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kinda of gross operations to change the pilot ID column
 * 1. Add an `pilot_id` column, which will get populated with the current ID
 * 2. Drop the `id` column, and then recreate it as a string field
 * 3. Iterate through all of the users and set their `id` to the `pilot_id`
 * 4. Change the other tables column types that reference `user_id`
 */
return new class() extends Migration {
    public function up()
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->unsignedBigInteger('pilot_id')
                ->after('id')
                ->unique()
                ->nullable()
                ->index('users_pilot_id');
        });

        DB::table('users')->update(['pilot_id' => DB::raw('`id`')]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pilot_id');
        });
    }
};
