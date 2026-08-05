<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * One row per filed PIREP holding a self-contained snapshot of its flight,
     * aircraft, and trimmed SimBrief plan, so detail views survive the source
     * rows being deleted or changed.
     */
    public function up(): void
    {
        if (Schema::hasTable('pirep_archive')) {
            return;
        }

        Schema::create('pirep_archive', function (Blueprint $table): void {
            $table->collation = 'utf8mb4_unicode_ci';
            $table->charset = 'utf8mb4';

            $table->string('pirep_id', 36)->primary();
            // Provenance: the flight the pirep was filed against, kept here so
            // it survives even if pireps.flight_id is ever cleared. Dangling
            // (flight hard-deleted) is fine; null means a manual pirep.
            $table->string('flight_id', 36)->nullable();
            $table->json('flight')->nullable();
            $table->json('aircraft')->nullable();
            $table->json('simbrief')->nullable();
            $table->json('navlog')->nullable();
            $table->timestamps();

            $table->foreign('pirep_id')->references('id')->on('pireps')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pirep_archive');
    }
};
