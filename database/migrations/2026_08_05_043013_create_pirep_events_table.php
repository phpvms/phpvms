<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pirep_events')) {
            return;
        }

        Schema::create('pirep_events', function (Blueprint $table): void {
            $table->collation = 'utf8mb4_unicode_ci';
            $table->charset = 'utf8mb4';

            $table->string('id', 36)->primary();
            $table->string('pirep_id', 36)->index();
            $table->string('acars_id', 36)->nullable();
            $table->string('client_event_id')->nullable();
            $table->string('type')->nullable()->index();
            $table->string('category')->index();
            $table->string('phase')->nullable();
            $table->text('log')->nullable();
            $table->json('details')->nullable();
            $table->string('sim_time')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['pirep_id', 'client_event_id']);

            // Defined inline (not via a later Schema::table()) so SQLite, which
            // cannot ALTER TABLE ADD CONSTRAINT, still gets the FK: it supports
            // foreign keys declared at CREATE TABLE time.
            $table->foreign('acars_id')->references('id')->on('acars')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pirep_events');
    }
};
