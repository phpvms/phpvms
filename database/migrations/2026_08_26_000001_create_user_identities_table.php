<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('connection_id', 64);
            $table->string('provider_user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('connection_id')->references('connection_id')->on('oauth_connections')->cascadeOnDelete();
            $table->unique(['connection_id', 'provider_user_id'], 'user_identities_connection_subject_unique');
            $table->unique(['user_id', 'connection_id'], 'user_identities_user_connection_unique');
        });

        Schema::create('user_identity_conflicts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('connection_id', 64);
            $table->string('provider_user_id');
            $table->json('user_ids');
            $table->string('reason', 64);
            $table->timestamps();

            $table->index(['connection_id', 'provider_user_id'], 'user_identity_conflicts_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identity_conflicts');
        Schema::dropIfExists('user_identities');
    }
};
