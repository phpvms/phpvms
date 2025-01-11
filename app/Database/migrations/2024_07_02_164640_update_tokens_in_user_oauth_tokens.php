<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('user_oauth_tokens', function (Blueprint $table) {
            $table->text('token')->change();
            $table->text('refresh_token')->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_oauth_tokens', function (Blueprint $table) {
            $table->string('token')->change();
            $table->string('refresh_token')->change();
        });
    }
};
