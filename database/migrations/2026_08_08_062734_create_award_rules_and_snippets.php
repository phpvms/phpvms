<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Rules-based awards: `awards.trigger` says when an award is evaluated;
     * the query-builder conditions tree lives in `award_rules` (one ruleset
     * per award); `award_snippets` is the library of reusable condition
     * fragments and `award_rule_snippet` records which snippets each ruleset
     * references, restricting snippet deletion at the database level while a
     * ruleset still uses it.
     *
     * Every step is guarded so a dev database that ran an earlier draft of
     * this (unshipped) migration — tree stored on `awards.conditions`, or the
     * `award_facts` / `award_rule_fact` pair the facts engine used — is
     * upgraded in place rather than needing a rebuild.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('awards', 'trigger')) {
            Schema::table('awards', function (Blueprint $table): void {
                $table->string('trigger', 16)->nullable()->after('ref_model_params');
            });
        }

        if (!Schema::hasTable('award_rules')) {
            Schema::create('award_rules', function (Blueprint $table): void {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->id();
                // `awards.id` is `increments()` (int unsigned), not `id()` --
                // the types must match exactly or MySQL 8 rejects the FK below.
                $table->unsignedInteger('award_id')->unique();
                $table->json('conditions');
                $table->timestamps();

                $table->foreign('award_id')->references('id')->on('awards')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('award_snippets')) {
            Schema::create('award_snippets', function (Blueprint $table): void {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->id();
                $table->string('name')->unique();
                $table->string('label');
                $table->string('description')->nullable();
                $table->json('conditions');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('award_rule_snippet')) {
            Schema::create('award_rule_snippet', function (Blueprint $table): void {
                $table->collation = 'utf8mb4_unicode_ci';
                $table->charset = 'utf8mb4';

                $table->unsignedBigInteger('award_rule_id');
                $table->unsignedBigInteger('award_snippet_id');

                $table->primary(['award_rule_id', 'award_snippet_id']);
                $table->foreign('award_rule_id')->references('id')->on('award_rules')->cascadeOnDelete();
                $table->foreign('award_snippet_id')->references('id')->on('award_snippets')->restrictOnDelete();
            });
        }

        if (Schema::hasColumn('awards', 'conditions')) {
            DB::table('awards')->whereNotNull('conditions')->orderBy('id')
                ->each(function (object $award): void {
                    DB::table('award_rules')->updateOrInsert(
                        ['award_id' => $award->id],
                        ['conditions' => $award->conditions, 'created_at' => now(), 'updated_at' => now()],
                    );
                });

            Schema::table('awards', function (Blueprint $table): void {
                $table->dropColumn('conditions');
            });
        }

        // The facts engine these two tables backed was replaced by snippets.
        Schema::dropIfExists('award_rule_fact');
        Schema::dropIfExists('award_facts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('award_rule_snippet');
        Schema::dropIfExists('award_snippets');
        Schema::dropIfExists('award_rules');

        if (Schema::hasColumn('awards', 'trigger')) {
            Schema::table('awards', function (Blueprint $table): void {
                $table->dropColumn(['trigger']);
            });
        }
    }
};
