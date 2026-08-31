<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('external_auth_sessions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('connection_id', 64);
            $table->string('provider_user_id');
            $table->string('oidc_sid')->nullable();
            $table->string('session_id')->unique();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('connection_id')->references('connection_id')->on('oauth_connections')->cascadeOnDelete();
            $table->index(['connection_id', 'provider_user_id'], 'external_auth_sessions_subject_index');
            $table->index(['connection_id', 'oidc_sid'], 'external_auth_sessions_sid_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_auth_sessions');
    }
};
