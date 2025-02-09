<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_oauth_tokens', function (Blueprint $table) {
            $table->renameColumn('last_refreshed_at', 'expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_oauth_tokens', function (Blueprint $table) {
            $table->renameColumn('last_refreshed_at', 'last_refreshed_at');
        });
    }
};
