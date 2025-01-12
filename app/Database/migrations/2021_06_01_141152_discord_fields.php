<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        // Delete the old Discord fields and then a webhook will get added
        DB::table('settings')
            ->where(['key' => 'notifications.discord_api_key'])
            ->delete();

        DB::table('settings')
            ->where(['key' => 'notifications.discord_public_channel_id'])
            ->delete();

        DB::table('settings')
            ->where(['key' => 'notifications.discord_public_channel_id'])
            ->delete();

        // Add a field to the user to enter their own Discord ID
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_id')
                ->default('')
                ->after('rank_id');
        });
    }
};
