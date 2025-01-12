<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        // Add a field to the user to enter their own Discord ID
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_private_channel_id')
                ->default('')
                ->after('discord_id');
        });
    }
};
